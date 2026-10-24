# AI Feed Enricher

Z neúplného feedu od dodávateľa urobí hotové produkty pre e-shop. Claude API spracuje každý produkt, výsledok skontroluje validátor a potom človek. Schválené produkty idú do WooCommerce.

```
XML / CSV feed  →  Claude (tool use, pevná JSON schéma)  →  validátor  →  kontrola človekom  →  WooCommerce REST / CSV
```

Ukážkový feed obsahuje to, čo dodávatelia bežne posielajú: názvy ako `STRIDEX TRAIL RUNNER X2 M GRY/ORG`, popisy po anglicky aj česky, skratky (`100% PES`, `W`, `blk`) a chýbajúce údaje.

## Čo z produktu vznikne

- slovenský názov, krátky a dlhý popis,
- zaradenie do **stromu kategórií e-shopu** (AI si nemôže vymyslieť novú kategóriu),
- pohlavie (pánske, dámske, detské, unisex),
- parametre (veľkosť, materiál, hmotnosť…) s preloženými hodnotami,
- SEO titulok a popis,
- **upozornenia** na to, čo v dátach chýba alebo nie je jasné. AI to nedopĺňa, len upozorní.

## Ako sa stráži kvalita

AI sa dá ľahko použiť, ťažšie je zaručiť, že nevymýšľa. Preto aplikácia kontroluje výstup v troch vrstvách:

1. **Pevná štruktúra výstupu.** Model musí odpovedať cez nástroj `save_product` s JSON schémou. Kategória a pohlavie sú `enum`, takže voľný text nejde.
2. **Validátor** (`app/Services/ProductValidator.php`):
   - **kontrola halucinácií**: každé číslo v názve a parametroch musí byť v dátach dodávateľa. Ak AI napíše „Hmotnosť 980 g“ a dodávateľ hmotnosť neuviedol, výstup neprejde. Čísla `1,15` a `1.15` sa porovnávajú ako rovnaké,
   - dĺžky polí (SEO titulok do 60 znakov, popis do 160),
   - kategória musí byť v strome, cena nesmie byť v názve.
3. **Oprava s chybami.** Keď validátor výstup odmietne, chyby sa vrátia modelu ako `tool_result` s `is_error: true`. Model ich vidí a opraví sa. Počet zachytených chýb je na prehľade feedu.

Po AI ešte všetko vidí človek: pôvodné dáta a návrh AI vedľa seba, každé pole sa dá upraviť, schváliť, zamietnuť alebo nechať vygenerovať znova.

## Export

- **WooCommerce REST API** (`wc/v3`): produkty sa založia ako **koncepty**. Kategórie sa dohľadajú alebo vytvoria aj s nadradenou kategóriou. Prihlásenie cez WordPress Application Password.
- **CSV pre importér WooCommerce** (Produkty → Importovať), ak priame napojenie nie je možné.

Na prehľade feedu sa ukazuje aj spotreba tokenov a cena za AI, ak je v `.env` zadaný cenník.

## Spustenie

Potrebné: PHP 8.3+, Composer, Node.js. Na vyskúšanie WooCommerce ešte Docker.

```bash
composer run setup              # závislosti, .env, migrácie, build
# do .env doplň ANTHROPIC_API_KEY
composer run dev                # server + queue worker + Vite
```

Otvor http://localhost:8000 a klikni na **Načítať ukážkový feed**, potom **Spracovať cez AI**.

Lokálny WooCommerce na http://localhost:8090:

```bash
docker/woocommerce/setup.sh     # vypíše WOO_URL, WOO_USER, WOO_APP_PASSWORD do .env
```

Z príkazového riadku:

```bash
php artisan feed:import resources/samples/supplier-feed.xml --process
```

## Testy

```bash
php artisan test
```

Claude API ani WooCommerce sa v testoch nevolajú, sú nahradené cez `Http::fake()`. Testuje sa:

- načítanie XML a CSV,
- validátor vrátane zachytenia halucinácie,
- odoslanie chyby modelu na opravu,
- spracovanie chyby API,
- úprava a schválenie človekom,
- CSV export,
- zápis do WooCommerce vrátane stromu kategórií.

## Štruktúra

| Súbor | Čo robí |
|---|---|
| `app/Services/FeedParser.php` | XML (formát Heureka) a CSV → jednotná štruktúra |
| `app/Services/ProductEnricher.php` | volanie Claude API, tool use, opakovanie s chybami |
| `app/Services/ProductValidator.php` | kontrola výstupu AI vrátane halucinácií |
| `app/Jobs/EnrichProduct.php` | spracovanie jedného produktu vo fronte |
| `app/Services/WooCommerceClient.php` | zápis do WooCommerce, kategórie |
| `app/Services/WooCsvExporter.php` | CSV pre importér WooCommerce |
| `config/enricher.php` | model, strom kategórií, cenník |

Stack: Laravel 13, PHP 8.5, SQLite, Tailwind 4. S Claude API sa komunikuje cez HTTP klienta Laravelu, bez ďalších balíkov.

Ukážkové dáta sú vymyslené, značky aj produkty sú fiktívne.

Autor: Michal Glonek · [github.com/glonco90](https://github.com/glonco90).
