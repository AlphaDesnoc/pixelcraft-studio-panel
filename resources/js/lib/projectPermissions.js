/** @param {Record<string, boolean>|null|undefined} perms */
export function canReadFeature(perms, feature) {
  if (!perms || Object.keys(perms).length === 0) {
    return true;
  }
  return perms[feature] !== false;
}

/** @param {Record<string, boolean>|null|undefined} perms */
export function canWriteFeature(perms, feature) {
  if (!canReadFeature(perms, feature)) {
    return false;
  }
  if (!perms || Object.keys(perms).length === 0) {
    return true;
  }
  const writeKey = `${feature}_write`;
  return perms[writeKey] !== false;
}

export function writeKeyFor(feature) {
  return `${feature}_write`;
}
