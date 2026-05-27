const { contextBridge, ipcRenderer } = require('electron');

let windowFocused = document.hasFocus();

window.addEventListener('focus', () => {
  windowFocused = true;
});

window.addEventListener('blur', () => {
  windowFocused = false;
});

contextBridge.exposeInMainWorld('pixelcraftDesktop', {
  isDesktop: true,
  isWindowFocused: () => windowFocused,
  setBadge: (count) => ipcRenderer.invoke('badge:set', count),
  showNotification: (payload) => ipcRenderer.invoke('notification:show', payload),
});
