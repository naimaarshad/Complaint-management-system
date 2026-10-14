var session = CMS.requireRole(["user"]);

if (session) {
  CMS.mountShell("my-complaints.html");

  var mine = CMS.getComplaints()
    .filter(function (c) {
      return c.userEmail === session.email;
    })
    .sort(function (a, b) {
      return new Date(b.created) - new Date(a.created);
    });

  function count(fn) {
    return mine.filter(fn).length;
  }

  var open = count(function (c) {
    return c.status !== "Resolved" && c.status !== "Closed";
  });

  document.getElementById("stats").innerHTML =
    '<div class="stat-card"><div class="num">' + mine.length + '</div><div class="lbl">Total filed</div></div>' +
    '<div class="stat-card amber"><div class="num">' + open + '</div><div class="lbl">Still open</div></div>' +
    '<div class="stat-card moss"><div class="num">' + count(function (c) { return c.status === "Resolved"; }) + '</div><div class="lbl">Resolved, awaiting your feedback</div></div>' +
    '<div class="stat-card"><div class="num">' + count(function (c) { return c.status === "Closed"; }) + '</div><div class="lbl">Closed</div></div>';

  var rows = document.getElementById("rows");
  var search = document.getElementById("search");
  var statusFilter = document.getElementById("statusFilter");

  function render() {
    var term = search.value.trim().toLowerCase();
    var status = statusFilter.value;
    var shown = mine.filter(function (c) {
      var matchesText = (c.id + " " + c.subject).toLowerCase().indexOf(term) !== -1;
      return matchesText && (status === "" || c.status === status);
    });

    if (shown.length === 0) {
      rows.innerHTML = '<tr><td colspan="6"><div class="empty-note">' +
        (mine.length === 0 ? 'You have not filed any complaints yet. <a href="select-org.html">File your first complaint</a>.' : "No complaints match these filters.") +
        "</div></td></tr>";
      return;
    }

    rows.innerHTML = shown
      .map(function (c) {
        return (
          '<tr class="row-link" data-id="' + CMS.esc(c.id) + '">' +
          '<td class="tnum">' + CMS.esc(c.id) + "</td>" +
          "<td>" + CMS.esc(c.subject) + "</td>" +
          "<td>" + CMS.esc(c.orgName) + "</td>" +
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
  render();
}
