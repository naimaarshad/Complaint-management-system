var form = document.getElementById("orgForm");
var message = document.getElementById("formMessage");
var docInput = document.getElementById("orgDoc");
var docLabel = document.getElementById("docLabel");

var fields = {
  orgName: document.getElementById("orgName"),
  sector: document.getElementById("sector"),
  city: document.getElementById("city"),
  contactName: document.getElementById("contactName"),
  email: document.getElementById("email"),
  phone: document.getElementById("phone"),
  orgDoc: docInput,
  password: document.getElementById("password"),
};

var rules = {
  orgName: function (v) {
    return v.trim().length < 3 ? "Enter the organization name." : "";
  },
  sector: function (v) {
    return v === "" ? "Choose a sector." : "";
  },
  city: function (v) {
    return v.trim().length < 2 ? "Enter the city." : "";
  },
  contactName: function (v) {
    return v.trim().length < 3 ? "Enter the contact person's full name." : "";
  },
  email: function (v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : "Enter a valid email address.";
  },
  phone: function (v) {
    return /^[0-9+\-\s]{7,16}$/.test(v.trim()) ? "" : "Enter a valid phone number.";
  },
  orgDoc: function () {
    return docInput.files.length === 0 ? "Attach a registration or authorization document." : "";
  },
  password: function (v) {
    return v.length < 8 ? "Use at least 8 characters." : "";
  },
};

function validateField(name) {
  var input = fields[name];
  var error = rules[name](input.value);
  input.closest(".field").classList.toggle("invalid", error !== "");
  document.getElementById(name + "Error").textContent = error;
  return error === "";
}

Object.keys(fields).forEach(function (name) {
  var eventName = name === "sector" || name === "orgDoc" ? "change" : "blur";
  fields[name].addEventListener(eventName, function () {
    validateField(name);
  });
});

docInput.addEventListener("change", function () {
  var file = docInput.files[0];
  docLabel.firstChild.textContent = file ? "Attached: " + file.name : "Click to attach a PDF or image";
});

form.addEventListener("submit", function (event) {
  event.preventDefault();

  var allValid = Object.keys(fields).map(validateField).every(Boolean);
  if (!allValid) return;

  var users = CMS.read(CMS.KEYS.users, []);
  var email = fields.email.value.trim().toLowerCase();

  if (
    users.some(function (u) {
      return u.email === email;
    })
  ) {
    message.className = "form__message";
    message.style.color = "#b3261e";
    message.textContent = "An account with this email already exists. Log in instead.";
    return;
  }

  var requests = CMS.getRequests();
  var request = {
    id: "REQ-" + Date.now(),
    orgName: fields.orgName.value.trim(),
    sector: fields.sector.value,
    city: fields.city.value.trim(),
    contactName: fields.contactName.value.trim(),
    email: email,
    phone: fields.phone.value.trim(),
    docName: docInput.files[0].name,
    status: "pending",
    submittedAt: new Date().toISOString(),
  };
  requests.push(request);
  CMS.saveRequests(requests);

  users.push({
    name: request.contactName,
    email: email,
    phone: request.phone,
    password: fields.password.value,
    role: "org",
    requestId: request.id,
  });
  CMS.write(CMS.KEYS.users, users);

  CMS.setSession({ name: request.contactName, email: email, role: "org", requestId: request.id, orgId: null });

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "Registration submitted. Redirecting…";
  form.querySelector(".btn").disabled = true;

  setTimeout(function () {
    window.location.href = "org-pending.html";
  }, 800);
});
