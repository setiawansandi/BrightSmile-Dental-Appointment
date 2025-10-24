const loginToggle = document.getElementById("login-toggle");
const signupToggle = document.getElementById("signup-toggle");
const loginForm = document.getElementById("login-form");
const signupForm = document.getElementById("signup-form");

const phoneInput = document.getElementById("phone");
if (phoneInput) {
  const iti = window.intlTelInput(phoneInput, {
    hiddenInput: "phone",
    initialCountry: "auto",
    geoIpLookup: function (success, failure) {
      fetch("https://ipapi.co/json/")
        .then((res) => res.json())
        .then((data) => success(data.country_code))
        .catch(() => success("us"));
    },
    separateDialCode: true,
    autoPlaceholder: "polynomial",
    utilsScript:
      "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
  });

  const phoneErrorSpan = document.getElementById("signup-phone-error");
  const phoneGroup = phoneInput.closest(".input-group");

  phoneInput.addEventListener("blur", function () {
    if (phoneInput.value.trim()) {
      if (iti.isValidNumber()) {
        phoneGroup.classList.remove("has-error");
        phoneErrorSpan.textContent = "";
      } else {
        phoneGroup.classList.add("has-error");
        phoneErrorSpan.textContent = "Please enter a valid phone number.";
      }
    } else {
      phoneGroup.classList.remove("has-error");
      phoneErrorSpan.textContent = "";
    }
  });
}

const successMessage = document.querySelector(".form-success-message");

const loginEmailSpan = document.getElementById("login-email-error");
const loginEmailPHPError = loginEmailSpan.textContent.trim();

const signupEmailSpan = document.getElementById("signup-email-error");
const signupEmailPHPError = signupEmailSpan.textContent.trim();

// Validation for login
const loginEmailInput = document.getElementById("login-email");
if (loginEmailInput) {
  const loginEmailGroup = loginEmailInput.closest(".input-group");

  loginEmailInput.addEventListener("blur", function () {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const email = loginEmailInput.value;

    if (email === "") {
      loginEmailGroup.classList.remove("has-error");
      loginEmailSpan.textContent = "";
      return;
    } else if (!emailRegex.test(email)) {
      loginEmailSpan.textContent = "Email is invalid";
      loginEmailGroup.classList.add("has-error");
      return;
    } else {
      loginEmailGroup.classList.remove("has-error");
      loginEmailSpan.textContent = "";
    }
  });
}

// Validation for register
const signupEmailInput = document.getElementById("signup-email");
if (signupEmailInput) {
  const signupEmailGroup = signupEmailInput.closest(".input-group");

  signupEmailInput.addEventListener("blur", function () {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const email = signupEmailInput.value;

    if (email === "") {
      signupEmailGroup.classList.remove("has-error");
      signupEmailSpan.textContent = "";
      return;
    } else if (!emailRegex.test(email)) {
      signupEmailSpan.textContent = "Email is invalid";
      signupEmailGroup.classList.add("has-error");
      return;
    } else {
      signupEmailGroup.classList.remove("has-error");
      signupEmailSpan.textContent = "";
    }
  });
}

// Validation for name
const firstNameInput = document.getElementById("first-name");
const lastNameInput = document.getElementById("last-name");

if (firstNameInput && lastNameInput) {
  const firstNameErrorSpan = document.getElementById("signup-name-error");
  const firstNameGroup = firstNameInput.closest(".input-group");
  const lastNameGroup = lastNameInput.closest(".input-group");
  const nameRegex = /^[\p{L}\s]+$/u;

  const validateNames = function () {
    const firstValue = firstNameInput.value;
    const lastValue = lastNameInput.value;

    const isFirstInvalid = firstValue !== "" && !nameRegex.test(firstValue);
    const isLastInvalid = lastValue !== "" && !nameRegex.test(lastValue);

    if (isFirstInvalid || isLastInvalid) {
      firstNameErrorSpan.textContent = "Must consist of letters only";
      firstNameGroup.classList.add("has-error");
      lastNameGroup.classList.add("has-error");
    } else {
      firstNameErrorSpan.textContent = "";
      firstNameGroup.classList.remove("has-error");
      lastNameGroup.classList.remove("has-error");
    }
  };

  firstNameInput.addEventListener("input", validateNames);
  lastNameInput.addEventListener("input", validateNames);
}

// Validation for password
const signupPasswordInput = document.getElementById("signup-password");
const reqList = document.getElementById("signup-req-list");

if (signupPasswordInput && reqList) {
  const reqs = {
    length: document.getElementById("req-length"),
    lower: document.getElementById("req-lower"),
    upper: document.getElementById("req-upper"),
    number: document.getElementById("req-number"),
    symbol: document.getElementById("req-symbol"),
  };

  signupPasswordInput.addEventListener("input", () => {
    const value = signupPasswordInput.value;

    if (value.length >= 8) {
      reqs.length.classList.add("valid");
    } else {
      reqs.length.classList.remove("valid");
    }

    if (/[a-z]/.test(value)) {
      reqs.lower.classList.add("valid");
    } else {
      reqs.lower.classList.remove("valid");
    }

    if (/[A-Z]/.test(value)) {
      reqs.upper.classList.add("valid");
    } else {
      reqs.upper.classList.remove("valid");
    }

    if (/[0-9]/.test(value)) {
      reqs.number.classList.add("valid");
    } else {
      reqs.number.classList.remove("valid");
    }

    if (/[^\p{L}\p{N}]/u.test(value)) {
      reqs.symbol.classList.add("valid");
    } else {
      reqs.symbol.classList.remove("valid");
    }
  });
}

// Prevent for date input
const dobInput = document.getElementById("dob");
if (dobInput) {
  dobInput.addEventListener("keydown", function (event) {
    if (event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
      event.preventDefault();
    }
  });

  dobInput.addEventListener("click", function () {
    try {
      dobInput.showPicker();
    } catch (error) {
      console.error("Error showing date picker:", error);
    }
  });
}

// Sign up button toggle
signupToggle.addEventListener("click", () => {
  const loginInputs = loginForm.querySelectorAll("input");
  loginInputs.forEach((input) => (input.value = ""));

  loginForm.querySelectorAll(".input-group.has-error").forEach((group) => {
    group.classList.remove("has-error");
  });
  loginForm.querySelectorAll(".error-message").forEach((span) => {
    span.textContent = "";
  });

  signupForm.classList.remove("hidden");
  loginForm.classList.add("hidden");
  signupToggle.classList.add("active");
  loginToggle.classList.remove("active");

  if (successMessage) {
    successMessage.style.display = "none";
  }
});

// Login button toggle
loginToggle.addEventListener("click", () => {
  const signupInputs = signupForm.querySelectorAll("input");
  signupInputs.forEach((input) => (input.value = ""));

  signupForm.querySelectorAll(".input-group.has-error").forEach((group) => {
    group.classList.remove("has-error");
  });
  signupForm.querySelectorAll(".error-message").forEach((span) => {
    span.textContent = "";
  });

  loginForm.classList.remove("hidden");
  signupForm.classList.add("hidden");
  loginToggle.classList.add("active");
  signupToggle.classList.remove("active");
});

// Password toggle
const passwordWrappers = document.querySelectorAll(".password-wrapper");
passwordWrappers.forEach((wrapper) => {
  const passwordInput = wrapper.querySelector("input");
  const eyeIcon = wrapper.querySelector(".eye-icon");
  const eyeSlashIcon = wrapper.querySelector(".eye-slash-icon");
  const toggle = wrapper.querySelector(".toggle-password");

  toggle.addEventListener("click", function () {
    const type =
      passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);

    eyeIcon.classList.toggle("hidden");
    eyeSlashIcon.classList.toggle("hidden");
  });
});

// page load
const urlParams = new URLSearchParams(window.location.search);

if (urlParams.has("signup_errors")) {
  signupForm.classList.remove("hidden");
  loginForm.classList.add("hidden");
  signupToggle.classList.add("active");
  loginToggle.classList.remove("active");
} else if (urlParams.has("signup") && urlParams.get("signup") === "success") {
  loginForm.classList.remove("hidden");
  signupForm.classList.add("hidden");
  loginToggle.classList.add("active");
  signupToggle.classList.remove("active");
} else if (urlParams.has("login_error")) {
  loginForm.classList.remove("hidden");
  signupForm.classList.add("hidden");
  loginToggle.classList.add("active");
  signupToggle.classList.remove("active");
}
