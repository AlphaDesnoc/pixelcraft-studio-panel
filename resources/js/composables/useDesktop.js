export function isDesktopApp() {
  return Boolean(window.pixelcraftDesktop?.isDesktop);
}

export function isDesktopWindowFocused() {
  if (!isDesktopApp()) {
    return true;
  }

  return window.pixelcraftDesktop.isWindowFocused?.() ?? document.hasFocus();
}

export function setDesktopBadge(count) {
  if (!isDesktopApp()) {
    return;
  }

  window.pixelcraftDesktop.setBadge?.(count);
}

export function showDesktopNotification({ title, body, url }) {
  if (!isDesktopApp() || isDesktopWindowFocused()) {
    return;
  }

  window.pixelcraftDesktop.showNotification?.({
    title,
    body,
    url: url ? absolutePanelUrl(url) : undefined,
  });
}

function absolutePanelUrl(pathOrUrl) {
  if (!pathOrUrl) {
    return undefined;
  }

  if (/^https?:\/\//i.test(pathOrUrl)) {
    return pathOrUrl;
  }

  return `${window.location.origin}${pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`}`;
}
