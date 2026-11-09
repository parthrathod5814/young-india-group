/* =========================================================
   Admin Login  |  login.js
   Small helpers for the login page only. Each feature is its
   own function, and everything checks that the login form
   exists, so you can paste this into admin.js safely.
   (The password is checked by login.php on the server.)
   ========================================================= */

/* ---------- 1. SHOW / HIDE PASSWORD ---------- */
function initPasswordToggle() {
  const button = document.getElementById("togglePassword");
  const input = document.getElementById("loginPassword");
  if (!button || !input) return;

  button.addEventListener("click", () => {
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    button.setAttribute("aria-label", show ? "Hide password" : "Show password");
    button.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
    input.focus();
  });
}


/* ---------- 2. CAPS LOCK WARNING ---------- */
function initCapsWarning() {
  const input = document.getElementById("loginPassword");
  const warning = document.getElementById("capsWarning");
  if (!input || !warning) return;

  const check = (event) => { warning.hidden = !(event.getModifierState && event.getModifierState("CapsLock")); };
  input.addEventListener("keyup", check);
  input.addEventListener("keydown", check);
  input.addEventListener("blur", () => { warning.hidden = true; });
}


/* ---------- 3. CHECK THE FORM BEFORE IT IS SENT ---------- */
function initLoginForm() {
  const form = document.getElementById("loginForm");
  if (!form) return;

  const email = document.getElementById("loginEmail");
  const password = document.getElementById("loginPassword");
  const submit = document.getElementById("loginSubmit");

  const setError = (input, message) => {
    const field = input.closest(".login-field");
    field.classList.toggle("has-error", !!message);
    field.querySelector(".login-error").textContent = message;
    return !message;
  };

  // Remove the red border as soon as the person starts typing again
  [email, password].forEach((input) => input.addEventListener("input", () => setError(input, "")));

  form.addEventListener("submit", (event) => {
    const emailOk = setError(email, /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()) ? "" : "Enter a valid email address.");
    const passwordOk = setError(password, password.value ? "" : "Enter your password.");

    if (!emailOk || !passwordOk) {
      event.preventDefault();
      (emailOk ? password : email).focus();
      return;
    }

    // Stop double clicks while the server checks the password
    submit.disabled = true;
    submit.querySelector("span").textContent = "Signing in...";
  });

  // Page came back from the browser's Back button: enable the button again
  window.addEventListener("pageshow", () => {
    submit.disabled = false;
    submit.querySelector("span").textContent = "Login to Console";
  });
}


/* ---------- START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  initPasswordToggle();
  initCapsWarning();
  initLoginForm();
});