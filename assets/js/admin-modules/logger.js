window.vaptScriptLoaded = true;
window.VAPT_DEBUG = window.VAPT_DEBUG || false;

window.vaptLog = window.vaptLog || {
  log: (...args) => window.VAPT_DEBUG && console.log('[VAPT]', ...args),
  warn: (...args) => window.VAPT_DEBUG && console.warn('[VAPT]', ...args),
  error: (...args) => console.error('[VAPT]', ...args),
  debug: (...args) => window.VAPT_DEBUG && console.debug('[VAPT]', ...args),
  info: (...args) => window.VAPT_DEBUG && console.info('[VAPT]', ...args)
};
