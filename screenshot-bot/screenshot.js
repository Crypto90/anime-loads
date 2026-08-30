const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    const screenshotsDir = path.join(__dirname, '..', 'screenshots');
    if (!fs.existsSync(screenshotsDir)){
        fs.mkdirSync(screenshotsDir);
    }

    try {
        // 1. Dashboard (Top)
        await page.goto('http://localhost:8080/index.php?user=admin&pass=admin', { waitUntil: 'domcontentloaded' });
        await new Promise(r => setTimeout(r, 4000)); // wait for AJAX to load completely (JD remote, etc)
        await page.screenshot({ path: path.join(screenshotsDir, 'dashboard.png') });

        // 2. Dashboard (Bottom)
        await page.evaluate(() => {
            window.scrollBy(0, 800);
        });
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: path.join(screenshotsDir, 'dashboard_bottom.png') });

        // 3. Setup Wizard
        await page.goto('http://localhost:8080/setup.php', { waitUntil: 'domcontentloaded' });
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: path.join(screenshotsDir, 'wizard.png') });

        // 4. Settings
        await page.goto('http://localhost:8080/settings.php', { waitUntil: 'domcontentloaded' });
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: path.join(screenshotsDir, 'settings.png') });

        // 5. Folder Manager
        await page.goto('http://localhost:8080/folder_manager.php', { waitUntil: 'domcontentloaded' });
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: path.join(screenshotsDir, 'folder_manager.png') });

        console.log("Screenshots captured successfully!");
    } catch (e) {
        console.error("Error capturing screenshots:", e);
    }

    await browser.close();
})();
