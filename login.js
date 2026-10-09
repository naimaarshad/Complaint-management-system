const form = document.getElementById("loginForm");
const message = document.getElementById("formMessage");

const fields = {
  email: document.getElementById("email"),
  password: document.getElementById("password"),
};

const rules = {
  email: (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : "Enter a valid email address."),
  password: (v) => (v.length === 0 ? "Enter your password." : ""),
};

function validateField(name) {
  const input = fields[name];
  const error = rules[name](input.value);
  input.closest(".field").classList.toggle("invalid", error !== "");
  document.getElementById(name + "Error").textContent = error;
  return error === "";
}

Object.keys(fields).forEach((name) => {
  fields[name].addEventListener("blur", () => validateField(name));
});

form.addEventListener("submit", (event) => {
  event.preventDefault();

  const allValid = Object.keys(fields).map(validateField).every(Boolean);
  if (!allValid) return;

  const users = JSON.parse(localStorage.getItem("users") || "[]");
  const email = fields.email.value.trim().toLowerCase();
  const user = users.find((u) => u.email === email && u.password === fields.password.value);

  if (!user) {
    message.className = "form__message";
    message.style.color = "#b3261e";
    message.textContent = "Wrong email or password.";
    return;
  }

  localStorage.setItem("currentUser", JSON.stringify({ name: user.name, email: user.email }));

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "Logged in. Redirecting…";
  form.querySelector(".btn").disabled = true;

  setTimeout(() => (window.location.href = "select-org.html"), 800);
});
