# Pubblicare una versione nuova su WordPress.org

Il plugin è nella directory di WordPress.org dal 25 ottobre 2026.

- SVN: https://plugins.svn.wordpress.org/mador (utente `khlumen`)
- Pagina pubblica: https://wordpress.org/plugins/mador
- Prima pubblicazione: 1.1.1, revisione SVN r3780686 (2 novembre 2026), uguale al tag git `v1.1.1` (eaa4338)

GitHub resta la sorgente. SVN riceve soltanto quello che va agli utenti: lo zip
prodotto da `git archive`, senza `languages/`, `tools/`, `README.md`, `.gitattributes`
e `docs/` (vedi `export-ignore` in `.gitattributes`).

## Struttura SVN

```
trunk/          ultima versione, uguale al tag più recente
tags/X.Y.Z/     una cartella per ogni versione rilasciata, mai modificata dopo il commit
assets/         icona, banner, screenshot: solo per la pagina della directory, non nel plugin
```

`assets/` contiene:

| File | Misura | Origine |
|---|---|---|
| `icon-128x128.png`, `icon-256x256.png` | 128, 256 | `mador-icona-app.svg` (geo-scan `public/brand/`) |
| `banner-772x250.png`, `banner-1544x500.png` | 772×250, 1544×500 | `mador-principale.svg` su fondo `#050505` |
| `screenshot-1.png` | 1280×640 | Impostazioni → Mador su WordPress locale, codice report vuoto |

I PNG hanno `svn:mime-type image/png`. Gli asset nuovi si aggiungono con lo stesso valore.
Uno screenshot ha una didascalia solo se compare nella sezione `== Screenshots ==` del
`readme.txt`. Oggi quella sezione non c'è.

## Passi per la versione X.Y.Z

1. **Nel repo git**, sul ramo della versione:
   - aggiorna `Version:` in `mador.php`. Se esiste una costante con la versione, aggiorna anche quella;
   - in `readme.txt` aggiorna `Stable tag: X.Y.Z`, aggiungi la voce in `== Changelog ==` e, se serve, in `== Upgrade Notice ==`;
   - se è uscita una WordPress nuova e il plugin è stato provato, aggiorna `Tested up to`;
   - fai passare Plugin Check senza errori, poi committa, fai il merge su `main`, crea il tag annotato `vX.Y.Z` e pushalo.

2. **Crea lo zip dal tag**:
   ```sh
   git archive --format=zip --prefix=mador/ vX.Y.Z -o mador-X.Y.Z.zip
   unzip -l mador-X.Y.Z.zip   # controlla: niente languages/, docs/, tools/, README.md
   ```

3. **Prepara la copia di lavoro SVN** in una cartella temporanea:
   ```sh
   svn co https://plugins.svn.wordpress.org/mador mador-svn
   unzip -q mador-X.Y.Z.zip -d rel
   # trunk = contenuto dello zip, con i file tolti rimossi anche da SVN
   rsync -a --delete --exclude .svn rel/mador/ mador-svn/trunk/
   cd mador-svn
   svn add --force trunk
   svn status trunk | grep '^!' | awk '{print $2}' | xargs -r svn rm
   # il tag nasce come copia di trunk
   svn cp trunk tags/X.Y.Z
   svn status
   ```
   Senza rsync, copia i file a mano. Poi usa `svn rm` per ogni file che non c'è più.

4. **Controlla** prima del commit:
   - `diff -r trunk ../rel/mador` non stampa nulla;
   - `Stable tag` in `trunk/readme.txt` è X.Y.Z;
   - `tags/X.Y.Z/` esiste.

   La directory legge `Stable tag` dal readme di `trunk`. Se punta a un tag che non esiste,
   gli utenti scaricano `trunk`.

5. **Commit**: la password SVN si genera su wordpress.org, in Profilo → Account & Security.
   Non è la password di accesso al sito.
   ```sh
   svn commit --username khlumen --no-auth-cache -m "Mador X.Y.Z"
   ```

6. **Verifica** dopo 5-15 minuti. Il primo import può richiedere fino a qualche ora.
   - https://wordpress.org/plugins/mador mostra la versione X.Y.Z;
   - l'API risponde con la versione nuova:
     `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=mador`
   - `https://downloads.wordpress.org/plugin/mador.X.Y.Z.zip` risponde 200;
   - i siti con il plugin vedono l'aggiornamento in Bacheca → Aggiornamenti. Possono servire fino a 12 ore.

## Solo readme o solo asset

- **Readme senza versione nuova** (per esempio `Tested up to`): modifica `readme.txt` sia in
  `trunk/` sia in `tags/<stable>/` e fai il commit. Non serve un tag nuovo.
- **Asset**: aggiungi o sostituisci i file in `assets/` e fai il commit. Le immagini
  vengono rilette dalla CDN dopo qualche minuto.

## Traduzioni

Le traduzioni non stanno nel plugin. Si caricano su translate.wordpress.org
(Import Translations) e arrivano agli utenti come language pack. I `.po` in `languages/`
restano nel repo come sorgente. Per i dettagli vedi `README.md`.
