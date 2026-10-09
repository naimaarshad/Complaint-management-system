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
  });
  localStorage.setItem("users", JSON.stringify(users));

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "Account created. Redirecting to login…";
  form.querySelector(".btn").disabled = true;

  setTimeout(() => (window.location.href = "login.html"), 1200);
});

if (document.getElementById("complaintRows")) {

  const user = JSON.parse(localStorage.getItem("currentUser") || "null");

  if (!user) {
    window.location.href = "login.html";
  } else {
    document.getElementById("userName").textContent = user.name;
    document.getElementById("userAvatar").textContent = user.name.charAt(0).toUpperCase();

    document.getElementById("userBtn").addEventListener("click", () => {
      if (confirm("Log out?")) {
        localStorage.removeItem("currentUser");
        window.location.href = "login.html";
      }
    });

    const complaints = [
      { id: "CMP-2461", subject: "Broken AC in Ward 4",              category: "Facilities", priority: "High",   status: "In progress", updated: "2 days ago" },
      { id: "CMP-2455", subject: "Billing discrepancy",              category: "Finance",    priority: "Medium", status: "Closed",      updated: "1 week ago" },
      { id: "CMP-2470", subject: "Staff unresponsive at front desk", category: "General",    priority: "Urgent", status: "Assigned",    updated: "5 hours ago" },
      { id: "CMP-2444", subject: "Request for wheelchair access",    category: "Facilities", priority: "Low",    status: "Submitted",   updated: "3 days ago" },
      { id: "CMP-2450", subject: "Lab report delay",                 category: "Medical",    priority: "Medium", status: "Closed",      updated: "2 weeks ago" },
    ];

    const slug = (text) => text.toLowerCase().replace(/\s+/g, "-");

    document.getElementById("complaintRows").innerHTML = complaints.map((c) => `
      <tr>
        <td>${c.id}</td>
        <td>${c.subject}</td>
        <td>${c.category}</td>
        <td><span class="badge p-${slug(c.priority)}">${c.priority}</span></td>
        <td><span class="badge s-${slug(c.status)}">${c.status}</span></td>
        <td>${c.updated}</td>
      </tr>
    `).join("");

    const closed   = complaints.filter((c) => c.status === "Closed").length;
    const progress = complaints.filter((c) => c.status === "In progress" || c.status === "Assigned").length;
    const urgent   = complaints.filter((c) => c.priority === "Urgent" && c.status !== "Closed").length;

    document.getElementById("statTotal").textContent = complaints.length;
    document.getElementById("statProgress").textContent = progress;
    document.getElementById("statUrgent").textContent = urgent;
    document.getElementById("statClosed").textContent = closed;
  }
}