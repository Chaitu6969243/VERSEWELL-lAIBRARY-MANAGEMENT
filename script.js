
const yearEl = document.getElementById('year');
if (yearEl) yearEl.textContent = new Date().getFullYear();


const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');

function setMenu(open) {
  if (!menuToggle || !mobileMenu) return;
  menuToggle.setAttribute('aria-expanded', String(open));
  if (open) {
    mobileMenu.hidden = false;
  
    requestAnimationFrame(() => mobileMenu.classList.add('open'));
  } else {
    mobileMenu.classList.remove('open');
    mobileMenu.addEventListener('transitionend', () => {
      mobileMenu.hidden = true;
    }, { once: true });
  }
}

if (menuToggle && mobileMenu) {
  menuToggle.addEventListener('click', () => {
    const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
    setMenu(!expanded);
  });


  mobileMenu.addEventListener('click', (e) => {
    const t = e.target;
    if (t instanceof HTMLElement && t.matches('[data-close="menu"]')) {
      setMenu(false);
    }
  });


  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
      setMenu(false);
      menuToggle.focus();
    }
  });
}


function smoothScrollTo(hash) {
  const target = document.querySelector(hash);
  if (!target) return;
  const header = document.querySelector('.site-header');
  const headerH = header ? header.getBoundingClientRect().height : 0;
  const y = target.getBoundingClientRect().top + window.scrollY - (headerH + 8);
  window.scrollTo({ top: y, behavior: 'smooth' });
}

document.addEventListener('click', (e) => {
  const a = e.target instanceof Element ? e.target.closest('a[href^="#"]') : null;
  if (!a) return;
  const href = a.getAttribute('href');
  if (!href || href === '#') return;
  e.preventDefault();
  smoothScrollTo(href);
});

// Reveal on scroll
const revealEls = document.querySelectorAll('.reveal');
if (revealEls.length) {
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  revealEls.forEach((el) => io.observe(el));
}

function updateAuthUI() {
  const isLoggedIn = localStorage.getItem('vw_signed_in') === '1';
  const isAdmin = localStorage.getItem('vw_is_admin') === '1';
  
  const profileLinks = document.querySelectorAll('.profile-link, .profile-mobile');
  const adminLinks = document.querySelectorAll('.admin-link, .admin-mobile');
  const authActions = document.querySelectorAll('.auth-action');
  
  if (isLoggedIn) {
    profileLinks.forEach(el => el.classList.remove('hidden'));
    if (isAdmin) {
      adminLinks.forEach(el => el.classList.remove('hidden'));
    }
    authActions.forEach(el => {
      el.textContent = 'Sign Out';
      el.onclick = (e) => {
        e.preventDefault();
        localStorage.clear();
        window.location.href = './auth.html';
      };
    });
  } else {
    profileLinks.forEach(el => el.classList.add('hidden'));
    adminLinks.forEach(el => el.classList.add('hidden'));
    authActions.forEach(el => {
      el.textContent = 'Sign In';
      el.onclick = null;
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateAuthUI();
});
