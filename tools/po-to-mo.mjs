/**
 * Compiles `languages/*.po` into `.mo`, after checking that the code and
 * the translation files agree.
 *
 *     node tools/po-to-mo.mjs            check, then write the .mo files
 *     node tools/po-to-mo.mjs --check    check only
 *
 * No gettext needed: the .mo format is a table of offsets. The check is
 * exact in both directions: every translatable string in the PHP code (and
 * the plugin header's Description) is in the .pot and in every .po, with a
 * non-empty translation, and no file carries a string the code no longer
 * uses. Plurals and contexts are not handled: the plugin has none.
 */
import { readFileSync, writeFileSync, readdirSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const HERE = dirname(fileURLToPath(import.meta.url));
const PLUGIN = resolve(HERE, "..");
const LANGUAGES = resolve(PLUGIN, "languages");
const DOMAIN = "mador";
const CHECK_ONLY = process.argv.includes("--check");

/** PHP files of the plugin, recursive. */
function phpFiles(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...phpFiles(p));
    else if (entry.name.endsWith(".php")) out.push(p);
  }
  return out;
}

/** Strings the code asks to translate, plus the header Description. */
function msgidsFromCode() {
  const found = new Set();
  const call = /\b(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'mador'\s*\)/g;
  for (const file of phpFiles(PLUGIN)) {
    const text = readFileSync(file, "utf8");
    let m;
    while ((m = call.exec(text))) {
      found.add(m[1].replace(/\\'/g, "'").replace(/\\\\/g, "\\"));
    }
  }
  const header = readFileSync(join(PLUGIN, "mador.php"), "utf8").match(/^\s*\*\s*Description:\s*(.+?)\s*$/m);
  if (header) found.add(header[1]);
  return found;
}

/** A .po file as a msgid → msgstr map. The header sits under the empty key. */
function readPo(path) {
  const entries = new Map();
  let key = null; // "msgid" | "msgstr"
  let msgid = null;
  let msgstr = null;

  const close = () => {
    if (msgid !== null) entries.set(msgid, msgstr ?? "");
    msgid = null;
    msgstr = null;
    key = null;
  };

  const unquote = (line) => {
    const inside = line.slice(line.indexOf('"') + 1, line.lastIndexOf('"'));
    return inside
      .replace(/\\n/g, "\n")
      .replace(/\\t/g, "\t")
      .replace(/\\"/g, '"')
      .replace(/\\\\/g, "\\");
  };

  for (const raw of readFileSync(path, "utf8").split(/\r?\n/)) {
    const line = raw.trim();
    if (line === "" || line.startsWith("#")) {
      if (line === "") close();
      continue;
    }
    if (line.startsWith("msgid ")) {
      close();
      msgid = unquote(line);
      msgstr = "";
      key = "msgid";
    } else if (line.startsWith("msgstr ")) {
      msgstr = unquote(line);
      key = "msgstr";
    } else if (line.startsWith('"')) {
      if (key === "msgid") msgid += unquote(line);
      else if (key === "msgstr") msgstr += unquote(line);
    }
  }
  close();
  return entries;
}

/** The .mo file: a 28-byte header, two offset tables, then the strings. */
function writeMo(entries, path) {
  const rows = [...entries.entries()]
    .map(([id, str]) => ({ id: Buffer.from(id, "utf8"), str: Buffer.from(str, "utf8") }))
    // Sorted: readers that binary-search need it.
    .sort((a, b) => Buffer.compare(a.id, b.id));

  const n = rows.length;
  const tablesStart = 28;
  const stringsStart = tablesStart + 16 * n;

  const idTable = Buffer.alloc(8 * n);
  const strTable = Buffer.alloc(8 * n);
  const parts = [];
  let offset = stringsStart;

  rows.forEach((r, i) => {
    idTable.writeUInt32LE(r.id.length, i * 8);
    idTable.writeUInt32LE(offset, i * 8 + 4);
    parts.push(r.id, Buffer.from([0]));
    offset += r.id.length + 1;
  });
  rows.forEach((r, i) => {
    strTable.writeUInt32LE(r.str.length, i * 8);
    strTable.writeUInt32LE(offset, i * 8 + 4);
    parts.push(r.str, Buffer.from([0]));
    offset += r.str.length + 1;
  });

  const head = Buffer.alloc(28);
  head.writeUInt32LE(0x950412de, 0); // magic
  head.writeUInt32LE(0, 4); // revision
  head.writeUInt32LE(n, 8);
  head.writeUInt32LE(tablesStart, 12);
  head.writeUInt32LE(tablesStart + 8 * n, 16);
  head.writeUInt32LE(0, 20); // no hash table
  head.writeUInt32LE(stringsStart, 24);

  writeFileSync(path, Buffer.concat([head, idTable, strTable, ...parts]));
  return n;
}

// ------------------------------------------------------------------ main

const fromCode = msgidsFromCode();
const poFiles = readdirSync(LANGUAGES).filter((f) => f.endsWith(".po"));
const pot = readPo(join(LANGUAGES, `${DOMAIN}.pot`));
let problems = 0;

const compare = (name, entries) => {
  const own = new Set([...entries.keys()].filter((k) => k !== ""));
  for (const id of fromCode) {
    if (!own.has(id)) {
      console.error(`✗ ${name}: missing string from the code — ${JSON.stringify(id)}`);
      problems++;
    }
  }
  for (const id of own) {
    if (!fromCode.has(id)) {
      console.error(`✗ ${name}: string the code no longer uses — ${JSON.stringify(id)}`);
      problems++;
    }
  }
};

compare(`${DOMAIN}.pot`, pot);

for (const file of poFiles) {
  const entries = readPo(join(LANGUAGES, file));
  compare(file, entries);
  for (const [id, str] of entries) {
    if (id !== "" && str === "") {
      console.error(`✗ ${file}: empty translation — ${JSON.stringify(id)}`);
      problems++;
    }
  }
}

if (problems > 0) {
  console.error(`\n${problems} mismatches: no .mo written.`);
  process.exit(1);
}

if (!CHECK_ONLY) {
  for (const file of poFiles) {
    const n = writeMo(readPo(join(LANGUAGES, file)), join(LANGUAGES, file.replace(/\.po$/, ".mo")));
    console.log(`✓ ${file} → ${file.replace(/\.po$/, ".mo")} (${n} entries)`);
  }
}

console.log(`✓ ${fromCode.size} strings in the code, all in ${poFiles.length + 1} language files.`);
