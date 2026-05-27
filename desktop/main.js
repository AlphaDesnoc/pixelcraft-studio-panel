const { app, BrowserWindow, shell, ipcMain, nativeImage, Notification } = require('electron');
const { autoUpdater } = require('electron-updater');
const path = require('path');
const fs = require('fs');

const USER_AGENT_SUFFIX = 'PixelCraftPanel/1.0';

let mainWindow = null;
let panelOrigin = null;

function loadConfig() {
  const defaults = {
    panelUrl:
      process.env.PANEL_URL ||
      process.env.DESKTOP_APP_URL ||
      'https://panel.pixelcraft-studios.fr',
  };

  const configPath = path.join(__dirname, 'config.json');

  if (fs.existsSync(configPath)) {
    try {
      const file = JSON.parse(fs.readFileSync(configPath, 'utf8'));
      return { ...defaults, ...file, panelUrl: file.panelUrl || defaults.panelUrl };
    } catch {
      return defaults;
    }
  }

  return defaults;
}

const config = loadConfig();

function resolvePanelOrigin() {
  try {
    return new URL(config.panelUrl).origin;
  } catch {
    return 'https://panel.pixelcraft-studios.fr';
  }
}

function isPanelUrl(url) {
  try {
    return new URL(url).origin === panelOrigin;
  } catch {
    return false;
  }
}

function createWindow() {
  panelOrigin = resolvePanelOrigin();

  mainWindow = new BrowserWindow({
    width: 1320,
    height: 900,
    minWidth: 960,
    minHeight: 640,
    title: 'PixelCraft Panel',
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      partition: 'persist:pixelcraft-panel',
    },
  });

  const baseUserAgent = mainWindow.webContents.getUserAgent();
  mainWindow.webContents.setUserAgent(`${baseUserAgent} ${USER_AGENT_SUFFIX}`);

  mainWindow.webContents.session.webRequest.onBeforeSendHeaders(
    { urls: [`${panelOrigin}/*`] },
    (details, callback) => {
      details.requestHeaders['X-PixelCraft-Desktop'] = '1';
      callback({ requestHeaders: details.requestHeaders });
    },
  );

  mainWindow.webContents.on('did-finish-load', () => {
    if (!mainWindow?.isVisible()) {
      mainWindow.show();
    }
  });

  mainWindow.webContents.on('will-navigate', (event, url) => {
    if (!isPanelUrl(url)) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (isPanelUrl(url)) {
      mainWindow.loadURL(url);
      return { action: 'deny' };
    }

    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.session.on('will-download', (_event, item) => {
    item.setSaveDialogOptions({
      title: 'Enregistrer le fichier',
    });
  });

  mainWindow.on('page-title-updated', (event, title) => {
    event.preventDefault();
    const suffix = title && title !== 'PixelCraft Panel' ? ` — ${title}` : '';
    mainWindow.setTitle(`PixelCraft Panel${suffix}`);
  });

  mainWindow.loadURL(config.panelUrl);

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

function setBadgeCount(count) {
  const value = Math.max(0, Number(count) || 0);

  if (process.platform === 'darwin' && app.dock) {
    app.dock.setBadge(value > 0 ? String(value) : '');
    return;
  }

  if (!mainWindow || mainWindow.isDestroyed()) {
    return;
  }

  if (value <= 0) {
    mainWindow.setOverlayIcon(null, '');
    return;
  }

  const badge = nativeImage.createFromDataURL(createBadgeDataUrl(value));
  mainWindow.setOverlayIcon(badge, `${value} non lu(s)`);
}

function createBadgeDataUrl(count) {
  const label = count > 99 ? '99+' : String(count);
  const size = 16;
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
      <circle cx="8" cy="8" r="8" fill="#ef4444"/>
      <text x="8" y="11" text-anchor="middle" fill="white" font-size="9" font-family="Segoe UI, sans-serif">${label}</text>
    </svg>`;

  return `data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`;
}

function showNativeNotification({ title, body, url }) {
  if (!Notification.isSupported()) {
    return false;
  }

  const notification = new Notification({
    title: title || 'PixelCraft Panel',
    body: body || '',
    silent: false,
  });

  notification.on('click', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) {
        mainWindow.restore();
      }
      mainWindow.show();
      mainWindow.focus();
    }

    if (url && isPanelUrl(url)) {
      mainWindow?.loadURL(url);
    }
  });

  notification.show();
  return true;
}

ipcMain.handle('window:isFocused', () => {
  return Boolean(mainWindow?.isFocused());
});

ipcMain.handle('badge:set', (_event, count) => {
  setBadgeCount(count);
  return true;
});

ipcMain.handle('notification:show', (_event, payload) => {
  return showNativeNotification(payload ?? {});
});

function initAutoUpdater() {
  if (!app.isPackaged) {
    return;
  }

  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on('update-downloaded', (info) => {
    showNativeNotification({
      title: 'Mise à jour PixelCraft Panel',
      body: `La version ${info.version} est prête. Redémarrez l'application pour l'installer.`,
    });
  });

  autoUpdater.on('error', (error) => {
    console.warn('[auto-updater]', error?.message ?? error);
  });

  autoUpdater.checkForUpdatesAndNotify().catch((error) => {
    console.warn('[auto-updater] check failed', error?.message ?? error);
  });
}

const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) {
        mainWindow.restore();
      }
      mainWindow.focus();
    }
  });

  app.whenReady().then(() => {
    if (process.platform === 'win32') {
      app.setAppUserModelId('fr.pixelcraftstudios.panel');
    }

    createWindow();
    initAutoUpdater();

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
      }
    });
  });
}

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
