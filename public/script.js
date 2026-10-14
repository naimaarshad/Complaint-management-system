// ---------- 1. Grab the elements ----------
const form = document.getElementById("signupForm");
const message = document.getElementById("formMessage");

const fields = {
  fullName: document.getElementById("fullName"),
  email:    document.getElementById("email"),
  phone:    document.getElementById("phone"),
  password: document.getElementById("password"),
};

// ---------- 2. Validation rules: each returns "" if OK, or an error text ----------
const rules = {
  fullName: (v) => (v.trim().length < 3 ? "Enter your full name." : ""),
  email:    (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : "Enter a valid email address."),
  phone:    (v) => (/^03\d{2}-?\d{7}$/.test(v.trim()) ? "" : "Use the format 03xx-xxxxxxx."),
  password: (v) => (v.length < 8 ? "Use at least 8 characters." : ""),
};

// ---------- 3. Show / clear an error on one field ----------
function validateField(name) {
  const input = fields[name];
  const error = rules[name](input.value);
  input.closest(".field").classList.toggle("invalid", error !== "");
  document.getElementById(name + "Error").textContent = error;
  return error === "";
}

// Re-check a field as soon as the user leaves it
Object.keys(fields).forEach((name) => {
  fields[name].addEventListener("blur", () => validateField(name));
});

// ---------- 4. Handle submit ----------
form.addEventListener("submit", (event) => {
  event.preventDefault(); // stop the page from reloading

  // Check every field (don't stop at the first bad one)
  const allValid = Object.keys(fields).map(validateField).every(Boolean);
  if (!allValid) return;

  // Save the user (browser only, for practice. A real app sends this to a server.)
  const users = JSON.parse(localStorage.getItem("users") || "[]");
  const email = fields.email.value.trim().toLowerCase();

  if (users.some((u) => u.email === email)) {
    message.className = "form__message";
    message.style.color = "#b3261e";
    message.textContent = "An account with this email already exists. Log in instead.";
    return;
  }

  users.push({
    name: fields.fullName.value.trim(),
    email,
    phone: fields.phone.value.trim(),
    password: fields.password.value, // NEVER store plain passwords in a real app
    role: "user",
  });
  localStorage.setItem("users", JSON.stringify(users));

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "Account created. Redirecting to login…";
  form.querySelector(".btn").disabled = true;

  setTimeout(() => (window.location.href = "login.php"), 1200);
});