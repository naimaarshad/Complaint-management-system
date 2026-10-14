var session = CMS.requireRole(["user", "org"]);
var complaint = session ? CMS.findComplaint(CMS.param("id")) : null;

var allowed =
  complaint &&
  ((session.role === "user" && complaint.userEmail === session.email) ||
    (session.role === "org" && complaint.orgId === session.orgId));

if (session && !allowed) {
  window.location.replace(CMS.homeFor(session));
} else if (session) {
  var isOrg = session.role === "org";
  CMS.mountShell(isOrg ? "org-dashboard.html" : "my-complaints.html");

  var back = document.getElementById("backLink");
  back.href = isOrg ? "org-dashboard.html" : "my-complaints.html";
  back.innerHTML = isOrg ? "&larr; Back to dashboard" : "&larr; Back to my complaints";

  function persist() {
    var all = CMS.getComplaints().map(function (c) {
      return c.id === complaint.id ? complaint : c;
    });
    CMS.saveComplaints(all);
  }

  function renderDetails() {
    document.getElementById("pageTitle").textContent = complaint.id;
    document.getElementById("pageSub").textContent = complaint.orgName;
    document.getElementById("dSubject").textContent = complaint.subject;
    document.getElementById("dDescription").textContent = complaint.description;

    var rows = [
      ["Status", CMS.statusBadge(complaint.status)],
      ["Priority", CMS.priorityBadge(complaint.priority)],
      ["Category", CMS.esc(complaint.category)],
      ["Organization", CMS.esc(complaint.orgName)],
      ["Filed by", CMS.esc(complaint.userName)],
      ["Filed on", CMS.fmtDate(complaint.created)],
    ];
    if (complaint.attachment) rows.push(["Attachment", CMS.esc(complaint.attachment)]);

    document.getElementById("meta").innerHTML = rows
      .map(function (r) {
        return "<div><dt>" + r[0] + "</dt><dd>" + r[1] + "</dd></div>";
      })
      .join("");

    var reached = CMS.STATUS_FLOW.indexOf(complaint.status);
    var items = complaint.updates.map(function (u) {
      return (
        '<li><div class="tl-title">' + CMS.esc(u.status) + "</div>" +
        '<div class="tl-meta">' + CMS.esc(u.by) + " · " + CMS.fmtDateTime(u.at) + "</div>" +
        (u.text ? '<div class="tl-text">' + CMS.esc(u.text) + "</div>" : "") +
        "</li>"
      );
    });
    CMS.STATUS_FLOW.forEach(function (s, i) {
      if (i > reached) items.push('<li class="pending"><div class="tl-title">' + s + "</div></li>");
    });
    document.getElementById("timeline").innerHTML = items.join("");
  }

  function renderFeedback() {
    var card = document.getElementById("feedbackCard");
    var body = document.getElementById("feedbackBody");
    var finished = complaint.status === "Resolved" || complaint.status === "Closed";

    if (isOrg || !finished) {
      if (isOrg && complaint.rating) {
        card.style.display = "block";
        card.querySelector("h3").textContent = "Complainant feedback";
        body.innerHTML =
          '<div class="stars-static">' + "★".repeat(complaint.rating) + "☆".repeat(5 - complaint.rating) + "</div>" +
          (complaint.feedback ? "<p>" + CMS.esc(complaint.feedback) + "</p>" : "");
      } else {
        card.style.display = "none";
      }
      return;
    }

    card.style.display = "block";

    if (complaint.rating) {
      body.innerHTML =
        '<div class="stars-static">' + "★".repeat(complaint.rating) + "☆".repeat(5 - complaint.rating) + "</div>" +
        (complaint.feedback ? "<p>" + CMS.esc(complaint.feedback) + "</p>" : "<p>Thanks for your rating.</p>");
      return;
    }

    body.innerHTML =
      '<div class="stars" id="stars">' +
      [1, 2, 3, 4, 5].map(function (n) {
        return '<button type="button" data-n="' + n + '" aria-label="' + n + ' stars">★</button>';
      }).join("") +
      "</div>" +
      '<form class="app-form" id="feedbackForm" novalidate>' +
      '<div class="field"><textarea id="feedbackText" placeholder="Anything you would like to add? (optional)" style="min-height: 80px;"></textarea></div>' +
      '<button type="submit" class="btn-primary">Send feedback</button>' +
      '<p class="form__message" id="feedbackMessage" role="status"></p>' +
      "</form>";

    var chosen = 0;
    var stars = body.querySelectorAll("#stars button");

    function paint() {
      stars.forEach(function (b, i) {
        b.classList.toggle("on", i < chosen);
      });
    }

    stars.forEach(function (b) {
      b.addEventListener("click", function () {
        chosen = parseInt(b.getAttribute("data-n"), 10);
        paint();
      });
    });

    document.getElementById("feedbackForm").addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = document.getElementById("feedbackMessage");
      if (chosen === 0) {
        msg.style.color = "#b3261e";
        msg.textContent = "Choose a star rating first.";
        return;
      }
      complaint.rating = chosen;
      complaint.feedback = document.getElementById("feedbackText").value.trim();
      persist();
      renderFeedback();
    });
  }

  function renderOrgActions() {
    if (!isOrg) return;
    document.getElementById("orgActions").style.display = "block";

    var statusSelect = document.getElementById("uStatus");
    var prioritySelect = document.getElementById("uPriority");

    statusSelect.innerHTML = CMS.STATUS_FLOW.map(function (s) {
      return "<option" + (s === complaint.status ? " selected" : "") + ">" + s + "</option>";
    }).join("");

    prioritySelect.innerHTML = CMS.PRIORITIES.map(function (p) {
      return "<option" + (p === complaint.priority ? " selected" : "") + ">" + p + "</option>";
    }).join("");

    document.getElementById("updateForm").addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = document.getElementById("updateMessage");
      var text = document.getElementById("uMessage").value.trim();
      var changedStatus = statusSelect.value !== complaint.status;
      var changedPriority = prioritySelect.value !== complaint.priority;

      if (!changedStatus && !changedPriority && text === "") {
        msg.style.color = "#b3261e";
        msg.textContent = "Change the status or priority, or write a message.";
        return;
      }

      complaint.status = statusSelect.value;
      complaint.priority = prioritySelect.value;
      if (changedStatus || text !== "") {
        complaint.updates.push({
          at: new Date().toISOString(),
          by: complaint.orgName,
          status: complaint.status,
          text: text,
        });
      }
      persist();
      document.getElementById("uMessage").value = "";
      msg.className = "form__message success";
      msg.style.color = "";
      msg.textContent = "Saved.";
      renderDetails();
      renderFeedback();
    });
  }

  if (CMS.param("new") === "1") {
    document.getElementById("notice").innerHTML =
      '<div class="notice">Your complaint was submitted. Your tracking ID is ' + CMS.esc(complaint.id) +
      ". You can follow progress here or from My complaints.</div>";
  }

  renderDetails();
  renderFeedback();
  renderOrgActions();
}
