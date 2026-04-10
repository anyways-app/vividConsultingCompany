/**
 * vividConsulting.info — Auth-Aware Navigation
 *
 * Include this script on every page to show either a "Sign In" link
 * or the user's avatar + dropdown in the header nav.
 *
 * Usage: Add before </body> on every page:
 *   <script src="js/auth-nav.js"></script>
 *
 * Requirements:
 *   - The desktop nav must have an element with id="auth-nav-desktop"
 *   - The mobile menu must have an element with id="auth-nav-mobile"
 *
 * If those elements don't exist, the script will look for the last
 * nav item and append after it (graceful fallback).
 */

(function () {
  'use strict';

  // ── Signed-out HTML ──────────────────────────────────────
  const signInDesktop = `
    <a href="login.html" class="ml-4 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white text-sm font-semibold rounded-lg transition-colors" id="auth-signin-btn">
      Sign In
    </a>`;

  const signInMobile = `
    <div class="pt-3" id="auth-mobile-signin">
      <a href="login.html" class="block text-center px-5 py-3 bg-accent hover:bg-accent-dark text-white font-semibold rounded-lg transition-colors">Sign In</a>
    </div>`;

  // ── Signed-in HTML (populated dynamically) ───────────────
  function avatarDesktopHtml(user) {
    const initial = (user.given_name || user.email || 'U').charAt(0).toUpperCase();
    const avatarSrc = user.avatar_url
      ? user.avatar_url
      : "data:image/svg+xml," + encodeURIComponent(
          '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"><rect fill="%2300b1ba" width="32" height="32" rx="16"/><text x="16" y="20" text-anchor="middle" fill="white" font-size="14" font-family="Inter,sans-serif">' + initial + '</text></svg>'
        );

    return `
      <div class="relative ml-4" id="auth-user-dropdown-wrap">
        <button class="flex items-center gap-2 focus:outline-none" onclick="this.parentElement.classList.toggle('auth-dropdown-open')">
          <img src="${avatarSrc}" alt="${escHtml(user.display_name || '')}" class="w-8 h-8 rounded-full border-2 border-navy-700 object-cover">
          <span class="hidden lg:block text-sm font-medium text-steel-300">${escHtml(user.given_name || user.display_name || 'Account')}</span>
          <svg class="hidden lg:block w-4 h-4 text-steel-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="auth-nav-dropdown absolute right-0 mt-2 w-56 bg-white border border-navy-700 rounded-lg shadow-xl py-2 z-50" style="display:none;">
          <div class="px-4 py-2 border-b border-navy-700">
            <p class="text-sm font-medium text-steel-100">${escHtml(user.display_name || user.email)}</p>
            <p class="text-xs text-steel-500 truncate">${escHtml(user.email)}</p>
          </div>
          <a href="dashboard.html" class="block px-4 py-2.5 text-sm text-steel-300 hover:text-steel-100 hover:bg-navy-700 transition-colors">Dashboard</a>
          <div class="border-t border-navy-700 mt-1 pt-1">
            <a href="api/logout.php" class="block px-4 py-2.5 text-sm text-red-500 hover:text-red-600 hover:bg-navy-700 transition-colors">Sign Out</a>
          </div>
        </div>
      </div>`;
  }

  function avatarMobileHtml(user) {
    return `
      <div class="border-t border-navy-700 my-3"></div>
      <div class="px-4 py-2">
        <p class="text-sm font-medium text-steel-100">${escHtml(user.display_name || user.email)}</p>
        <p class="text-xs text-steel-500">${escHtml(user.email)}</p>
      </div>
      <a href="dashboard.html" class="block px-4 py-3 text-steel-300 hover:text-slate-900 hover:bg-navy-700 rounded-lg transition-colors">Dashboard</a>
      <a href="api/logout.php" class="block px-4 py-3 text-red-500 hover:text-red-600 hover:bg-navy-700 rounded-lg transition-colors">Sign Out</a>`;
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  // ── Inject into page ─────────────────────────────────────
  function injectAuth(user) {
    // Desktop: replace Subscribe button or append after nav
    const desktopNav = document.querySelector('header nav.hidden.lg\\:flex');
    if (desktopNav) {
      // Remove existing Subscribe button if present
      const subscribeBtn = desktopNav.querySelector('a[href*="templates"]');
      // Find the last ml-4 subscribe link
      const allLinks = desktopNav.querySelectorAll('a');
      const lastLink = allLinks[allLinks.length - 1];
      if (lastLink && lastLink.textContent.trim() === 'Subscribe') {
        lastLink.remove();
      }

      if (user) {
        desktopNav.insertAdjacentHTML('beforeend', avatarDesktopHtml(user));
        // Toggle dropdown
        setupDropdown();
      } else {
        desktopNav.insertAdjacentHTML('beforeend', signInDesktop);
      }
    }

    // Mobile: inject at bottom of mobile nav
    const mobileNav = document.querySelector('#mobileMenu nav');
    if (mobileNav) {
      // Remove existing Subscribe block at bottom
      const existingSubscribe = mobileNav.querySelector('div.pt-3');
      if (existingSubscribe) existingSubscribe.remove();

      if (user) {
        mobileNav.insertAdjacentHTML('beforeend', avatarMobileHtml(user));
      } else {
        mobileNav.insertAdjacentHTML('beforeend', signInMobile);
      }
    }
  }

  function setupDropdown() {
    document.addEventListener('click', function (e) {
      const wrap = document.getElementById('auth-user-dropdown-wrap');
      if (!wrap) return;
      const menu = wrap.querySelector('.auth-nav-dropdown');
      if (!menu) return;

      if (wrap.classList.contains('auth-dropdown-open') && !wrap.contains(e.target)) {
        wrap.classList.remove('auth-dropdown-open');
        menu.style.display = 'none';
      } else if (wrap.contains(e.target)) {
        const isOpen = menu.style.display !== 'none';
        menu.style.display = isOpen ? 'none' : 'block';
      }
    });
  }

  // ── Check auth status ────────────────────────────────────
  fetch('api/user.php', { credentials: 'same-origin' })
    .then(function (res) {
      if (res.ok) return res.json();
      return null;
    })
    .then(function (user) {
      injectAuth(user);
    })
    .catch(function () {
      injectAuth(null);
    });
})();
