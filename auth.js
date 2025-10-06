const tabLogin = document.getElementById('tab-login');
const tabSignup = document.getElementById('tab-signup');
const panelLogin = document.getElementById('panel-login');
const panelSignup = document.getElementById('panel-signup');

function activateTab(which) {
  const loginActive = which === 'login';
  const signupActive = which === 'signup';

  tabLogin.classList.toggle('active', loginActive);
  tabSignup.classList.toggle('active', signupActive);
  tabLogin.setAttribute('aria-selected', String(loginActive));
  tabSignup.setAttribute('aria-selected', String(signupActive));
  
  if (panelLogin) panelLogin.hidden = !loginActive;
  if (panelSignup) panelSignup.hidden = !signupActive;
  
  if (panelLogin) panelLogin.classList.toggle('active', loginActive);
  if (panelSignup) panelSignup.classList.toggle('active', signupActive);
  
  if (loginActive && panelLogin) {
    const emailInput = panelLogin.querySelector('input[name="email"]');
    if (emailInput) emailInput.focus();
  } else if (signupActive && panelSignup) {
    const nameInput = panelSignup.querySelector('input[name="name"]');
    if (nameInput) nameInput.focus();
  }
}

if (tabLogin && tabSignup) {
  tabLogin.addEventListener('click', () => activateTab('login'));
  tabSignup.addEventListener('click', () => activateTab('signup'));
}

document.addEventListener('click', (e) => {
  const btn = e.target instanceof Element ? e.target.closest('[data-switch]') : null;
  if (!btn) return;
  const dest = btn.getAttribute('data-switch');
  if (dest === 'signup') activateTab('signup');
  if (dest === 'login') activateTab('login');
});

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;

function setError(input, message) {
  const field = input.closest('.field');
  const error = field ? field.querySelector('.error') : null;
  if (error) error.textContent = message || '';
  input.classList.toggle('invalid', Boolean(message));
}

function clearErrors(form) {
  form.querySelectorAll('input').forEach(i => setError(i, ''));
}

function validateLogin(form) {
  let ok = true;
  const email = form.elements.namedItem('email');
  const password = form.elements.namedItem('password');

  if (email instanceof HTMLInputElement) {
    if (!email.value || !emailRegex.test(email.value)) {
      setError(email, 'Please enter a valid email address.');
      ok = false;
    } else {
      setError(email, '');
    }
  }

  if (password instanceof HTMLInputElement) {
    if (!password.value || password.value.length < 6) {
      setError(password, 'Password must be at least 6 characters.');
      ok = false;
    } else {
      setError(password, '');
    }
  }

  return ok;
}

function validateSignup(form) {
  let ok = true;
  const name = form.elements.namedItem('name');
  const email = form.elements.namedItem('email');
  const password = form.elements.namedItem('password');
  if (name instanceof HTMLInputElement) {
    if (!name.value || name.value.trim().length < 2) { setError(name, 'Please enter your full name.'); ok = false; } else setError(name, '');
  }
  if (email instanceof HTMLInputElement) {
    if (!email.value || !emailRegex.test(email.value)) { setError(email, 'Enter a valid email.'); ok = false; } else setError(email, '');
  }
  if (password instanceof HTMLInputElement) {
    if (!password.value || password.value.length < 8) { setError(password, 'Password must be at least 8 characters.'); ok = false; } else setError(password, '');
  }
  return ok;
}

const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');

function showFormError(form, message) {
    const existingError = form.querySelector('.form-error');
    if (existingError) {
        existingError.textContent = message;
        return;
    }
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'form-error';
    errorDiv.textContent = message;
    form.insertBefore(errorDiv, form.firstChild);
}

function showFormSuccess(form, message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'form-success';
    successDiv.textContent = message;
    form.insertBefore(successDiv, form.firstChild);
}

if (signupForm) {
    signupForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors(signupForm);
        
        const formError = signupForm.querySelector('.form-error');
        if (formError) formError.remove();
        
        if (!validateSignup(signupForm)) return;
        
        const formData = new FormData(signupForm);
        const userData = {
            name: formData.get('name'),
            email: formData.get('email'),
            password: formData.get('password'),
            email_notifications: true,
            sms_notifications: false
        };

    const submitBtn = signupForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    try {
        const resp = await fetch('auth_handler.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ action: 'register', ...userData })
        });

      let result;
      try {
        result = await resp.json();
      } catch (jsonErr) {
        throw new Error('Invalid server response');
      }

      if (!resp.ok) {
        // If server returns field-level errors, map them to inputs
        if (result && typeof result === 'object') {
          if (result.field && signupForm.querySelector(`[name="${result.field}"]`)) {
            setError(signupForm.querySelector(`[name="${result.field}"]`), result.message || result.error || 'Invalid value');
            throw new Error(result.message || result.error || 'Validation error');
          }
        }
        throw new Error(result.error || 'Registration failed');
      }

      showFormSuccess(signupForm, 'Registration successful! Redirecting...');
      signupForm.reset();

      // If server returned a user/session, auto-login in the UI
      if (result && result.user) {
        localStorage.setItem('vw_signed_in', '1');
        localStorage.setItem('vw_user_id', String(result.user.id));
        localStorage.setItem('vw_user_email', result.user.email || '');
        localStorage.setItem('vw_user_name', result.user.name || '');
        if (result.token) localStorage.setItem('token', result.token);
        // Redirect to profile or home
        setTimeout(() => { window.location.href = 'profile.html'; }, 800);
        return;
      }

      // Otherwise switch to login panel after a brief delay
      setTimeout(() => {
        activateTab('login');
        const successMessage = signupForm.querySelector('.form-success');
        if (successMessage) successMessage.remove();
      }, 1200);

    } catch (error) {
      console.error('Registration error:', error);
      showFormError(signupForm, error.message || 'Registration failed. Please try again.');
    } finally {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
    });
}

if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors(loginForm);
        
        const formError = loginForm.querySelector('.form-error');
        if (formError) formError.remove();
        
        if (!validateLogin(loginForm)) return;
        
        const formData = new FormData(loginForm);
        
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    try {
        const resp = await fetch('auth_handler.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'login', email: formData.get('email'), password: formData.get('password') })
        });

      let result;
      try { result = await resp.json(); } catch (j) { throw new Error('Invalid server response'); }

      if (!resp.ok) {
        if (result && result.field && loginForm.querySelector(`[name="${result.field}"]`)) {
          setError(loginForm.querySelector(`[name="${result.field}"]`), result.message || result.error || 'Invalid value');
          throw new Error(result.message || result.error || 'Login failed');
        }
        throw new Error(result.error || 'Login failed');
      }

      // Store the authentication data for the UI (support session-based and token-based responses)
      if (result.user) {
        localStorage.setItem('vw_signed_in', '1');
        localStorage.setItem('vw_user_id', String(result.user.id));
        localStorage.setItem('vw_user_email', result.user.email || '');
        localStorage.setItem('vw_user_name', result.user.name || '');
        localStorage.setItem('user', JSON.stringify(result.user));
      }
      if (result.token) {
        localStorage.setItem('token', result.token);
      }

      // Redirect to home page
      window.location.href = 'index.html';
    } catch (error) {
      console.error('Login error:', error);
      showFormError(loginForm, error.message || 'Login failed. Please check your credentials.');
    } finally {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
    });
}

// Note: Login and signup handlers above use fetch() to communicate with auth_handler.php.
// Older localStorage-based handlers were removed so requests reach the server reliably
// and are not cancelled by client-side redirects.

// Initialize with some demo users if none exist
window.addEventListener('DOMContentLoaded', () => {
  const existingUsers = JSON.parse(localStorage.getItem('vw_users') || '[]');
  if (existingUsers.length === 0) {
    const demoUsers = [
      {
        id: 'demo1',
        name: 'John Doe',
        email: 'john@example.com',
        password: 'password123',
        created_at: new Date().toISOString()
      },
      {
        id: 'demo2',
        name: 'Jane Smith',
        email: 'jane@example.com',
        password: 'password123',
        created_at: new Date().toISOString()
      }
    ];
    localStorage.setItem('vw_users', JSON.stringify(demoUsers));
  }

  const last = localStorage.getItem('vw_last_email');
  const loginEmail = document.querySelector('#panel-login input[name="email"]');
  if (last && loginEmail instanceof HTMLInputElement) {
    loginEmail.value = last;
  }

  const signedIn = localStorage.getItem('vw_signed_in') === '1';
  const signInButton = document.querySelector('.signin-btn');
  const signOutButton = document.querySelector('.signout-btn');

  if (signedIn) {
    if (signInButton) signInButton.style.display = 'none';
    if (signOutButton) signOutButton.style.display = 'block';
  } else {
    if (signInButton) signInButton.style.display = 'block';
    if (signOutButton) signOutButton.style.display = 'none';
  }

  if (signOutButton) {
    signOutButton.addEventListener('click', () => {
      localStorage.removeItem('vw_signed_in');
      localStorage.removeItem('vw_user_id');
      localStorage.removeItem('vw_user_email');
      localStorage.removeItem('vw_user_name');
      localStorage.removeItem('token');
      window.location.href = 'index.html';
    });
  }
});


