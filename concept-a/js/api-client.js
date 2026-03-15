// ==================== PUREX API CLIENT ====================
// Shared across all pages — uses HttpOnly cookie for JWT (XSS-proof)
// + CSRF double-submit token for state-changing requests

const API_BASE = '/api';

// ==================== SAFE REDIRECT UTILITIES ====================
// These prevent open-redirect vulnerabilities. Use them instead of raw
// window.location.href = ... or window.open(...) with dynamic URLs.

/**
 * Allowlist of internal paths that are valid redirect targets.
 * Every redirect in the app must land on one of these, or pass the
 * strict relative-path validator below.
 */
const ALLOWED_REDIRECTS = new Set([
  '/', '/index.html', '/login.html', '/admin.html', '/shop.html',
  '/checkout.html', '/contact.html', '/about.html', '/sale.html',
  '/product.html', '/products/index.html', '/suppliers.html',
  '/customers.html', '/users.html', '/activity.html', '/audit.html',
  '/debug.html',
]);

/**
 * Validate that a URL string is a safe internal redirect target.
 * Blocks: absolute URLs, protocol-relative URLs, data: URIs,
 * javascript: URIs, backslash tricks, header injection via newlines.
 *
 * @param {string} url — The redirect target to validate
 * @returns {boolean} true if safe
 */
function isInternalPath(url) {
  if (typeof url !== 'string' || url.length === 0) return false;

  // Must start with /
  if (url[0] !== '/') return false;

  // Block // (protocol-relative → //evil.com redirects to evil.com)
  if (url[1] === '/') return false;

  // Block backslashes (/\evil.com works in some browsers)
  if (url.includes('\\')) return false;

  // Block protocol in any position (data:, javascript:, http://)
  if (/[a-z]:/i.test(url)) return false;

  // Block @ sign (/login@evil.com → browser may navigate to evil.com)
  if (url.includes('@')) return false;

  // Block newlines/nulls (header injection)
  if (/[\r\n\x00]/.test(url)) return false;

  return true;
}

/**
 * Perform a safe internal redirect. If the target fails validation,
 * redirects to the fallback (default: /).
 *
 * Usage:  safeRedirect('/admin.html');
 *         safeRedirect(userInput, '/shop.html');  // with custom fallback
 *
 * @param {string} path — The redirect target
 * @param {string} fallback — Where to go if validation fails (default: '/')
 */
function safeRedirect(path, fallback) {
  fallback = fallback || '/';

  // Check allowlist first (fast path, exact match)
  if (ALLOWED_REDIRECTS.has(path)) {
    window.location.href = path;
    return;
  }

  // Validate as a safe relative path
  if (isInternalPath(path)) {
    window.location.href = path;
    return;
  }

  // Validation failed — log and redirect to fallback
  console.warn('safeRedirect blocked:', path);
  window.location.href = fallback;
}

/**
 * Allowlist of external domains that window.open() is allowed to target.
 * Only these domains can be opened in a new tab from the app.
 */
const ALLOWED_EXTERNAL_DOMAINS = new Set([
  'wa.me',              // WhatsApp direct message
  'web.whatsapp.com',   // WhatsApp Web
  'api.whatsapp.com',   // WhatsApp API
]);

/**
 * Safely open an external URL in a new tab. Validates that the URL
 * points to an explicitly allowlisted external domain.
 *
 * Usage:  safeExternalOpen('https://wa.me/923400145796?text=Hello');
 *
 * @param {string} url — The external URL to open
 * @returns {Window|null} The opened window, or null if blocked
 */
function safeExternalOpen(url) {
  if (typeof url !== 'string' || url.length === 0) return null;

  try {
    const parsed = new URL(url);

    // Must be https (block http, javascript, data, etc.)
    if (parsed.protocol !== 'https:') {
      console.warn('safeExternalOpen blocked non-HTTPS:', url);
      return null;
    }

    // Domain must be in allowlist
    if (!ALLOWED_EXTERNAL_DOMAINS.has(parsed.hostname)) {
      console.warn('safeExternalOpen blocked domain:', parsed.hostname);
      return null;
    }

    // Safe — open with security attributes
    return window.open(url, '_blank', 'noopener,noreferrer');
  } catch (e) {
    // URL constructor throws on invalid URLs — block them
    console.warn('safeExternalOpen blocked invalid URL:', url);
    return null;
  }
}

const api = {
  // CSRF token — fetched on login and stored in memory (not accessible to XSS via cookie)
  _csrfToken: sessionStorage.getItem('purex_csrf') || '',

  getToken() {
    // JWT is now in HttpOnly cookie (browser sends automatically)
    // Keep sessionStorage token for backward compat during migration
    return sessionStorage.getItem('purex_token');
  },

  setToken(token) {
    sessionStorage.setItem('purex_token', token);
  },

  clearToken() {
    sessionStorage.removeItem('purex_token');
    sessionStorage.removeItem('purex_session');
    sessionStorage.removeItem('purex_csrf');
    this._csrfToken = '';
  },

  setCsrfToken(token) {
    this._csrfToken = token;
    sessionStorage.setItem('purex_csrf', token);
  },

  async request(method, path, body = null) {
    const headers = {};

    // Bearer token as fallback (HttpOnly cookie is sent automatically by browser)
    const token = this.getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;

    // CSRF token on state-changing requests
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && this._csrfToken) {
      headers['X-CSRF-Token'] = this._csrfToken;
    }

    const opts = { method, headers, credentials: 'same-origin' };
    if (body !== null && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body instanceof FormData) {
      opts.body = body; // Don't set Content-Type for FormData
    }

    try {
      const res = await fetch(API_BASE + path, opts);

      if (res.status === 401) {
        this.clearToken();
        if (!window.location.pathname.includes('login.html') && !window.location.pathname.includes('checkout.html')) {
          safeRedirect('/login.html');
        }
        return null;
      }

      // If CSRF token expired, refresh it and retry once
      if (res.status === 403) {
        const errData = await res.json().catch(() => ({}));
        if (errData.error && errData.error.includes('CSRF')) {
          const refreshed = await this._refreshCsrf();
          if (refreshed) {
            // Retry the original request with new CSRF token
            headers['X-CSRF-Token'] = this._csrfToken;
            const retry = await fetch(API_BASE + path, { ...opts, headers });
            if (retry.ok) return await retry.json();
          }
        }
        console.error('API Error:', errData.error || errData);
        return null;
      }

      const data = await res.json();
      if (!res.ok) {
        console.error('API Error:', data.error || data);
        return null;
      }
      return data;
    } catch (err) {
      console.error('API request failed:', err);
      return null;
    }
  },

  async _refreshCsrf() {
    try {
      const res = await fetch(API_BASE + '/auth/csrf', { credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        if (data.csrf_token) {
          this.setCsrfToken(data.csrf_token);
          return true;
        }
      }
    } catch(e) {}
    return false;
  },

  get(path) { return this.request('GET', path); },
  post(path, data) { return this.request('POST', path, data); },
  put(path, data) { return this.request('PUT', path, data); },
  patch(path, data) { return this.request('PATCH', path, data); },
  del(path) { return this.request('DELETE', path); },

  async upload(type, file) {
    const fd = new FormData();
    fd.append('file', file);
    return this.request('POST', '/uploads/' + type, fd);
  },

  // Auth helpers
  async login(username, password) {
    // Fetch CSRF token first (needed for the login POST itself — but login is exempt since no cookie yet)
    const result = await this.post('/auth/login', { username, password });
    if (result && result.token) {
      this.setToken(result.token);
      sessionStorage.setItem('purex_session', JSON.stringify(result.user));
      sessionStorage.setItem('purex_admin', 'true');
      // Store the CSRF token issued with login
      if (result.csrf_token) {
        this.setCsrfToken(result.csrf_token);
      }
    }
    return result;
  },

  async logout() {
    await this.post('/auth/logout', {}).catch(() => {});
    this.clearToken();
    sessionStorage.removeItem('purex_admin');
    safeRedirect('/login.html');
  },

  isLoggedIn() {
    return !!this.getToken();
  },

  getUser() {
    try {
      return JSON.parse(sessionStorage.getItem('purex_session') || '{}');
    } catch(e) { return {}; }
  }
};
