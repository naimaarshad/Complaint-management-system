var session = CMS.requireRole(["org"]);

if (session && session.orgId) {
  window.location.replace("org-dashboard.html");
} else if (session) {
  var request = CMS.getRequests().filter(function (r) {
    return r.id === session.requestId;
  })[0];

  var title = document.getElementById("statusTitle");
  var sub = document.getElementById("statusSub");
  var steps = document.getElementById("statusSteps");
  var actions = document.getElementById("statusActions");

  function step(text, state) {
    return '<li class="' + state + '">' + CMS.esc(text) + "</li>";
  }

  if (request && request.status === "approved") {
    session.orgId = request.orgId;
    CMS.setSession(session);
    title.textContent = "Your organization is approved";
    sub.textContent = request.orgName + " can now receive and manage complaints.";
    steps.innerHTML = step("Registration received", "done") + step("Documents reviewed", "done") + step("Approved", "done");
    actions.innerHTML = '<a class="btn-primary" href="org-dashboard.html">Go to dashboard</a>';
  } else if (request && request.status === "rejected") {
    title.textContent = "Registration not approved";
    sub.textContent = request.note || "We could not verify the documents you submitted.";
    steps.innerHTML = step("Registration received", "done") + step("Documents reviewed", "done") + step("Not approved", "current");
    actions.innerHTML = '<a class="btn-secondary" href="index.php">Back to home</a>';
  } else {
    title.textContent = "Your registration is under review";
    sub.textContent = (request ? request.orgName + " was submitted on " + CMS.fmtDate(request.submittedAt) + ". " : "") + "Approval usually takes under 2 business days.";
    steps.innerHTML = step("Registration received", "done") + step("Documents under review", "current") + step("Approved: dashboard access", "");
    actions.innerHTML =
      '<a class="btn-primary" href="org-pending.html">Check status</a>' +
      '<a class="btn-secondary" href="index.php">Back to home</a>';
  }

  var logout = document.createElement("a");
  logout.href = "#";
  logout.className = "btn-secondary";
  logout.textContent = "Log out";
  logout.addEventListener("click", function (e) {
    e.preventDefault();
    CMS.logout();
  });
  actions.appendChild(logout);
}
