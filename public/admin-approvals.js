var session = CMS.requireRole(["admin"]);

if (session) {
  CMS.mountShell("admin-approvals.html");

  function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
  }

  function decide(id, decision) {
    var requests = CMS.getRequests();
    var request = requests.filter(function (r) {
      return r.id === id;
    })[0];
    if (!request || request.status !== "pending") return;

    if (decision === "approve") {
      var orgs = CMS.getOrgs();
      var orgId = slug(request.orgName) + "-" + String(Date.now()).slice(-4);
      orgs.push({
        id: orgId,
        name: request.orgName,
        sector: request.sector,
        city: request.city,
        categories: CMS.DEFAULT_CATEGORIES,
      });
      CMS.write(CMS.KEYS.orgs, orgs);
      request.status = "approved";
      request.orgId = orgId;
    } else {
      request.status = "rejected";
      request.note = "We could not verify the documents you submitted. Please contact support.";
    }

    request.reviewedAt = new Date().toISOString();
    CMS.saveRequests(requests);
    render();
  }

  function render() {
    var requests = CMS.getRequests().sort(function (a, b) {
      return new Date(b.submittedAt) - new Date(a.submittedAt);
    });
    var pending = requests.filter(function (r) {
      return r.status === "pending";
    });
    var reviewed = requests.filter(function (r) {
      return r.status !== "pending";
    });
    var approved = reviewed.filter(function (r) {
      return r.status === "approved";
    }).length;

    document.getElementById("stats").innerHTML =
      '<div class="stat-card amber"><div class="num">' + pending.length + '</div><div class="lbl">Waiting for review</div></div>' +
      '<div class="stat-card moss"><div class="num">' + approved + '</div><div class="lbl">Approved</div></div>' +
      '<div class="stat-card brick"><div class="num">' + (reviewed.length - approved) + '</div><div class="lbl">Rejected</div></div>' +
      '<div class="stat-card"><div class="num">' + CMS.getOrgs().length + '</div><div class="lbl">Live organizations</div></div>';

    document.getElementById("pendingRows").innerHTML =
      pending.length === 0
        ? '<tr><td colspan="7"><div class="empty-note">Nothing waiting. New registrations will show up here.</div></td></tr>'
        : pending
            .map(function (r) {
              return (
                "<tr>" +
                '<td class="tnum">' + CMS.esc(r.orgName) + "</td>" +
                "<td>" + CMS.esc(r.sector) + "</td>" +
                "<td>" + CMS.esc(r.city) + "</td>" +
                "<td>" + CMS.esc(r.contactName) + "<br>" + CMS.esc(r.email) + "</td>" +
                "<td>" + CMS.esc(r.docName) + "</td>" +
                "<td>" + CMS.fmtDate(r.submittedAt) + "</td>" +
                '<td><button class="btn-primary" data-act="approve" data-id="' + CMS.esc(r.id) + '">Approve</button> ' +
                '<button class="btn-secondary" data-act="reject" data-id="' + CMS.esc(r.id) + '">Reject</button></td>' +
                "</tr>"
              );
            })
            .join("");

    document.getElementById("doneRows").innerHTML =
      reviewed.length === 0
        ? '<tr><td colspan="6"><div class="empty-note">No reviewed registrations yet.</div></td></tr>'
        : reviewed
            .map(function (r) {
              var badge =
                r.status === "approved"
                  ? '<span class="badge t-closed">Approved</span>'
                  : '<span class="badge t-urgent">Rejected</span>';
              return (
                "<tr>" +
                '<td class="tnum">' + CMS.esc(r.orgName) + "</td>" +
                "<td>" + CMS.esc(r.sector) + "</td>" +
                "<td>" + CMS.esc(r.city) + "</td>" +
                "<td>" + CMS.esc(r.contactName) + "</td>" +
                "<td>" + CMS.fmtDate(r.submittedAt) + "</td>" +
                "<td>" + badge + "</td>" +
                "</tr>"
              );
            })
            .join("");
  }

  document.getElementById("pendingRows").addEventListener("click", function (e) {
    var button = e.target.closest("button[data-act]");
    if (button) decide(button.getAttribute("data-id"), button.getAttribute("data-act"));
  });

  render();
}
