const form = document.getElementById("resetForm");
const message = document.getElementById("formMessage");
const emailInput = document.getElementById("email");

function validateEmail() {
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
  emailInput.closest(".field").classList.toggle("invalid", !ok);
  document.getElementById("emailError").textContent = ok ? "" : "Enter a valid email address.";
  return ok;
}

emailInput.addEventListener("blur", validateEmail);

form.addEventListener("submit", (event) => {
  event.preventDefault();
  if (!validateEmail()) return;

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "If an account exists for that email, a reset link has been sent.";
  form.querySelector(".btn").disabled = true;
});
