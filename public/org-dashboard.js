var session = CMS.requireRole(["org"]);

if (session && !session.orgId) {
  window.location.replace("org-pending.html");
} else if (session) {
  CMS.mountShell("org-dashboard.html");

  var org = CMS.findOrg(session.orgId);
  if (org) document.getElementById("pageTitle").textContent = org.name;

  var all = CMS.getComplaints()
    .filter(function (c) {
      return c.orgId === session.orgId;
    })
    .sort(function (a, b) {
      return new Date(b.created) - new Date(a.created);
    });

  function isOpen(c) {
    return c.status !== "Resolved" && c.status !== "Closed";
  }

  var urgentOpen = all.filter(function (c) {
    return isOpen(c) && c.priority === "Urgent";
  }).length;

  var waiting = all.filter(function (c) {
    return c.status === "Submitted";
  }).length;

  var done = all.filter(function (c) {
    return !isOpen(c);
  }).length;

  document.getElementById("stats").innerHTML =
    '<div class="stat-card"><div class="num">' + all.length + '</div><div class="lbl">Total received</div></div>' +
    '<div class="stat-card amber"><div class="num">' + waiting + '</div><div class="lbl">New, not yet assigned</div></div>' +
    '<div class="stat-card brick"><div class="num">' + urgentOpen + '</div><div class="lbl">Urgent and open</div></div>' +
    '<div class="stat-card moss"><div class="num">' + done + '</div><div class="lbl">Resolved or closed</div></div>';

  var rows = document.getElementById("rows");
  var search = document.getElementById("search");
  var statusFilter = document.getElementById("statusFilter");
  var priorityFilter = document.getElementById("priorityFilter");

  function render() {
    var term = search.value.trim().toLowerCase();
    var shown = all.filter(function (c) {
      var text = (c.id + " " + c.subject + " " + c.userName).toLowerCase();
      return (
        text.indexOf(term) !== -1 &&
        (statusFilter.value === "" || c.status === statusFilter.value) &&
        (priorityFilter.value === "" || c.priority === priorityFilter.value)
      );
    });

    if (shown.length === 0) {
      rows.innerHTML = '<tr><td colspan="7"><div class="empty-note">' +
        (all.length === 0 ? "No complaints yet. They will appear here as people file them." : "No complaints match these filters.") +
        "</div></td></tr>";
      return;
    }

    rows.innerHTML = shown
      .map(function (c) {
        return (
          '<tr class="row-link" data-id="' + CMS.esc(c.id) + '">' +
          '<td class="tnum">' + CMS.esc(c.id) + "</td>" +
          "<td>" + CMS.esc(c.subject) + "</td>" +
          "<td>" + CMS.esc(c.userName) + "</td>" +
          "<td>" + CMS.esc(c.category) + "</td>" +
          "<td>" + CMS.priorityBadge(c.priority) + "</td>" +
          "<td>" + CMS.statusBadge(c.status) + "</td>" +
          "<td>" + CMS.fmtDate(c.created) + "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  rows.addEventListener("click", function (e) {
    var row = e.target.closest("tr.row-link");
    if (row) window.location.href = "complaint-detail.html?id=" + encodeURIComponent(row.getAttribute("data-id"));
  });

  search.addEventListener("input", render);
  statusFilter.addEventListener("change", render);
  priorityFilter.addEventListener("change", render);
  render();
}
