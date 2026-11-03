# Captures des écrans de connexion pour public/guide-connexion (étapes 1, 2, 4 ; l'étape 3 est le mail du code).
# Usage : py scripts/guide_connexion_captures.py <dossier>, sur le Docker local (127.0.0.1:8084), puis conversion en JPEG qualité 82.
import pathlib, sys
from playwright.sync_api import sync_playwright

out = pathlib.Path(sys.argv[1]); out.mkdir(parents=True, exist_ok=True)
base = 'http://127.0.0.1:8084/'
with sync_playwright() as p:
    b = p.chromium.launch()
    for lang in ('fr', 'en'):
        pg = b.new_page(viewport={'width': 1100, 'height': 900}, device_scale_factor=1)
        pg.goto(f'{base}?lang={lang}')
        pg.fill('#username', 'prenom.nom@cfao.com')
        pg.fill('#password', 'motdepasse')
        pg.wait_for_timeout(600); bb = pg.locator('.login-card').bounding_box(); pg.screenshot(path=str(out / f'etape1_{lang}.png'), clip={'x': bb['x'] - 24, 'y': bb['y'] - 24, 'width': bb['width'] + 48, 'height': bb['height'] + 48})
        pg.goto(f'{base}?lien=1&motif=mail&lang={lang}')
        pg.fill('#magic_email', 'prenom.nom@cfao.com')
        pg.wait_for_timeout(600); bb = pg.locator('.login-card').bounding_box(); pg.screenshot(path=str(out / f'etape2_{lang}.png'), clip={'x': bb['x'] - 24, 'y': bb['y'] - 24, 'width': bb['width'] + 48, 'height': bb['height'] + 48})
        pg.click('button[name=magic_submit]')
        pg.wait_for_load_state()
        pg.fill('#login_code', '579 525')
        pg.wait_for_timeout(600); bb = pg.locator('.login-card').bounding_box(); pg.screenshot(path=str(out / f'etape4_{lang}.png'), clip={'x': bb['x'] - 24, 'y': bb['y'] - 24, 'width': bb['width'] + 48, 'height': bb['height'] + 48})
        pg.close()
    b.close()
print(sorted(x.name for x in out.iterdir()))
