var form = document.getElementById("loginForm");
var message = document.getElementById("formMessage");
var roleButtons = document.querySelectorAll("#roleSwitch button");
var role = "user";

var copy = {
  user: {
    title: "Log in",
    sub: "Log in to file and track your complaints.",
    button: "Log in",
    foot: 'Don\'t have an account? <a href="register.html" class="link-green">Register</a>',
  },
  org: {
    title: "Organization log in",
    sub: "Log in to manage the complaints your organization receives.",
    button: "Log in as organization",
    foot: 'New organization? <a href="register-org.html" class="link-green">Register your organization</a>',
  },
  admin: {
    title: "Platform admin",
    sub: "Review and approve organization registrations.",
    button: "Log in as admin",
    foot: '<a href="login.html" class="link-green">Back to normal log in</a>',
  },
};

var fields = {
  email: document.getElementById("email"),
  password: document.getElementById("password"),
};

var rules = {
  email: function (v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : "Enter a valid email address.";
  },
  password: function (v) {
    return v.length === 0 ? "Enter your password." : "";
  },
};

function setRole(next) {
  role = copy[next] ? next : "user";
  document.getElementById("loginTitle").textContent = copy[role].title;
  document.getElementById("loginSub").textContent = copy[role].sub;
  document.getElementById("loginBtn").textContent = copy[role].button;
  document.getElementById("loginFoot").innerHTML = copy[role].foot;
  document.getElementById("roleSwitch").style.display = role === "admin" ? "none" : "flex";
  roleButtons.forEach(function (b) {
    b.classList.toggle("active", b.getAttribute("data-role") === role);
  });
  message.textContent = "";
  var url = role === "user" ? "login.html" : "login.html?role=" + role;
  history.replaceState(null, "", url);
}

roleButtons.forEach(function (b) {
  b.addEventListener("click", function () {
    setRole(b.getAttribute("data-role"));
  });
});

document.getElementById("adminLink").addEventListener("click", function (e) {
  e.preventDefault();
  setRole("admin");
});

function validateField(name) {
  var input = fields[name];
  var error = rules[name](input.value);
  input.closest(".field").classList.toggle("invalid", error !== "");
  document.getElementById(name + "Error").textContent = error;
  return error === "";
}

Object.keys(fields).forEach(function (name) {
  fields[name].addEventListener("blur", function () {
    validateField(name);
  });
});

function showError(text) {
  message.className = "form__message";
  message.style.color = "#b3261e";
  message.textContent = text;
}

form.addEventListener("submit", function (event) {
  event.preventDefault();

  var allValid = Object.keys(fields).map(validateField).every(Boolean);
  if (!allValid) return;

  var users = CMS.read(CMS.KEYS.users, []);
  var email = fields.email.value.trim().toLowerCase();
  var user = users.filter(function (u) {
    return u.email === email && u.password === fields.password.value;
  })[0];

  if (!user) {
    showError("Wrong email or password.");
    return;
  }

  var userRole = user.role || "user";
  if (userRole !== role) {
    var names = { user: "a complainant", org: "an organization", admin: "a platform admin" };
    showError("This account is registered as " + names[userRole] + ". Switch the option above and try again.");
    return;
  }

  var session = { name: user.name, email: user.email, role: userRole };

  if (userRole === "org") {
    session.requestId = user.requestId || null;
    session.orgId = user.orgId || null;
    if (!session.orgId && user.requestId) {
      var request = CMS.getRequests().filter(function (r) {
        return r.id === user.requestId;
      })[0];
      if (request && request.status === "approved") session.orgId = request.orgId;
    }
  }

  CMS.setSession(session);

  message.style.color = "";
  message.className = "form__message success";
  message.textContent = "Logged in. Redirecting…";
  form.querySelector(".btn").disabled = true;

  setTimeout(function () {
    window.location.href = CMS.homeFor(session);
  }, 600);
});

var startRole = CMS.param("role");
setRole(startRole === "org" || startRole === "admin" ? startRole : "user");
