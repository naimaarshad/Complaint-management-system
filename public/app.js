(function () {
  var KEYS = {
    users: "users",
    orgs: "cms_orgs",
    complaints: "cms_complaints",
    requests: "cms_org_requests",
    session: "currentUser",
    seeded: "cms_seed_v1",
  };

  var STATUS_FLOW = ["Submitted", "Assigned", "In Progress", "Resolved", "Closed"];
  var PRIORITIES = ["Low", "Medium", "High", "Urgent"];
  var DEFAULT_CATEGORIES = ["Service quality", "Billing", "Staff behavior", "Facilities", "Other"];

  var STATUS_CLASS = {
    "Submitted": "t-submitted",
    "Assigned": "t-assigned",
    "In Progress": "t-progress",
    "Resolved": "t-resolved",
    "Closed": "t-closed",
  };

  var PRIORITY_CLASS = {
    "Low": "t-low",
    "Medium": "t-medium",
    "High": "t-high",
    "Urgent": "t-urgent",
  };

  function read(key, fallback) {
    try {
      var value = JSON.parse(localStorage.getItem(key));
      return value === null || value === undefined ? fallback : value;
    } catch (e) {
      return fallback;
    }
  }

  function write(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  function esc(text) {
    var map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(text === null || text === undefined ? "" : text).replace(/[&<>"']/g, function (c) {
      return map[c];
    });
  }

  function daysAgo(n) {
    return new Date(Date.now() - n * 86400000).toISOString();
  }

  function fmtDate(iso) {
    return new Date(iso).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
  }

  function fmtDateTime(iso) {
    return new Date(iso).toLocaleString("en-GB", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function statusBadge(status) {
    return '<span class="badge ' + (STATUS_CLASS[status] || "t-submitted") + '">' + esc(status) + "</span>";
  }

  function priorityBadge(priority) {
    return '<span class="badge ' + (PRIORITY_CLASS[priority] || "t-medium") + '">' + esc(priority) + "</span>";
  }

  function seed() {
    if (localStorage.getItem(KEYS.seeded)) return;

    write(KEYS.orgs, [
      {
        id: "riverside",
        name: "Riverside General Hospital",
        sector: "Healthcare",
        city: "Rawalpindi",
        categories: ["Billing", "Staff behavior", "Waiting time", "Medicine availability", "Cleanliness", "Other"],
      },
      {
        id: "northbridge",
        name: "Northbridge University",
        sector: "Education",
        city: "Islamabad",
        categories: ["Fees", "Faculty", "Exams and results", "Hostel", "Facilities", "Other"],
      },
    ]);

    write(KEYS.requests, [
      {
        id: "REQ-1001",
        orgName: "Greenfield Municipal Office",
        sector: "Government",
        city: "Lahore",
        contactName: "Hamza Tariq",
        email: "hamza@greenfield.gov.pk",
        phone: "0321-5550142",
        docName: "authorization-letter.pdf",
        status: "pending",
        submittedAt: daysAgo(1),
      },
    ]);

    write(KEYS.complaints, [
      {
        id: "CMP-2461",
        orgId: "riverside",
        orgName: "Riverside General Hospital",
        userEmail: "user@demo.com",
        userName: "Demo Complainant",
        category: "Waiting time",
        subject: "Two hour wait at the outpatient desk",
        description: "I arrived with an appointment at 10:00 but was only seen after 12:00. Nobody at the desk could tell me how long it would take.",
        priority: "High",
        status: "In Progress",
        attachment: "",
        created: daysAgo(6),
        updates: [
          { at: daysAgo(6), by: "Demo Complainant", status: "Submitted", text: "Complaint submitted." },
          { at: daysAgo(5), by: "Riverside General Hospital", status: "Assigned", text: "Assigned to the outpatient manager." },
          { at: daysAgo(3), by: "Riverside General Hospital", status: "In Progress", text: "We are reviewing the appointment schedule for that morning." },
        ],
      },
      {
        id: "CMP-2455",
        orgId: "riverside",
        orgName: "Riverside General Hospital",
        userEmail: "user@demo.com",
        userName: "Demo Complainant",
        category: "Billing",
        subject: "Wrong amount on my discharge bill",
        description: "The bill shows a private room for two nights but I stayed in the general ward.",
        priority: "Medium",
        status: "Resolved",
        attachment: "bill.jpg",
        created: daysAgo(14),
        updates: [
          { at: daysAgo(14), by: "Demo Complainant", status: "Submitted", text: "Complaint submitted." },
          { at: daysAgo(12), by: "Riverside General Hospital", status: "Assigned", text: "Sent to the billing department." },
          { at: daysAgo(9), by: "Riverside General Hospital", status: "Resolved", text: "Billing error confirmed. A corrected bill and refund have been issued." },
        ],
      },
      {
        id: "CMP-2470",
        orgId: "northbridge",
        orgName: "Northbridge University",
        userEmail: "user@demo.com",
        userName: "Demo Complainant",
        category: "Hostel",
        subject: "No running water in Block C",
        description: "There has been no water on the second floor of Block C for three days.",
        priority: "Medium",
        status: "Assigned",
        attachment: "",
        created: daysAgo(2),
        updates: [
          { at: daysAgo(2), by: "Demo Complainant", status: "Submitted", text: "Complaint submitted." },
          { at: daysAgo(1), by: "Northbridge University", status: "Assigned", text: "Forwarded to the hostel warden." },
        ],
      },
      {
        id: "CMP-2468",
        orgId: "riverside",
        orgName: "Riverside General Hospital",
        userEmail: "sara@demo.com",
        userName: "Sara Khan",
        category: "Medicine availability",
        subject: "Prescribed medicine unavailable at the pharmacy",
        description: "The pharmacy has been out of my prescribed medicine for a week and I cannot go without it.",
        priority: "Urgent",
        status: "Submitted",
        attachment: "",
        created: daysAgo(1),
        updates: [{ at: daysAgo(1), by: "Sara Khan", status: "Submitted", text: "Complaint submitted." }],
      },
      {
        id: "CMP-2444",
        orgId: "riverside",
        orgName: "Riverside General Hospital",
        userEmail: "sara@demo.com",
        userName: "Sara Khan",
        category: "Cleanliness",
        subject: "Dirty washrooms on the third floor",
        description: "The washrooms on the third floor were not cleaned for the whole weekend.",
        priority: "Low",
        status: "Closed",
        attachment: "",
        created: daysAgo(20),
        rating: 4,
        feedback: "Fixed quickly, thank you.",
        updates: [
          { at: daysAgo(20), by: "Sara Khan", status: "Submitted", text: "Complaint submitted." },
          { at: daysAgo(18), by: "Riverside General Hospital", status: "Assigned", text: "Housekeeping supervisor informed." },
          { at: daysAgo(16), by: "Riverside General Hospital", status: "Resolved", text: "Cleaning schedule updated." },
          { at: daysAgo(15), by: "Riverside General Hospital", status: "Closed", text: "Closed after feedback." },
        ],
      },
    ]);

    var users = read(KEYS.users, []);
    [
      { name: "Demo Complainant", email: "user@demo.com", phone: "0300-1234567", password: "password123", role: "user" },
      { name: "Riverside Admin", email: "org@riverside.com", phone: "0300-7654321", password: "password123", role: "org", orgId: "riverside" },
      { name: "Platform Admin", email: "admin@cms.com", phone: "", password: "admin12345", role: "admin" },
    ].forEach(function (demo) {
      var exists = users.some(function (u) {
        return u.email === demo.email;
      });
      if (!exists) users.push(demo);
    });
    write(KEYS.users, users);

    localStorage.setItem(KEYS.seeded, "1");
  }

  function session() {
    var s = read(KEYS.session, null);
    if (s && !s.role) s.role = "user";
    return s;
  }

  function setSession(s) {
    write(KEYS.session, s);
  }

  function logout() {
    localStorage.removeItem(KEYS.session);
    window.location.href = "logout.php";
  }

  function homeFor(s) {
    if (!s) return "login.php";
    if (s.role === "admin") return "admin-approvals.html";
    if (s.role === "org") return s.orgId ? "org-dashboard.html" : "org-pending.html";
    return "select-org.html";
  }

  function requireRole(roles) {
    var s = session();
    if (!s) {
      window.location.replace("login.php?role=" + roles[0]);
      return null;
    }
    if (roles.indexOf(s.role) === -1) {
      window.location.replace(homeFor(s));
      return null;
    }
    return s;
  }

  function getOrgs() {
    return read(KEYS.orgs, []);
  }

  function findOrg(id) {
    return getOrgs().filter(function (o) {
      return o.id === id;
    })[0];
  }

  function getComplaints() {
    return read(KEYS.complaints, []);
  }

  function saveComplaints(list) {
    write(KEYS.complaints, list);
  }

  function findComplaint(id) {
    return getComplaints().filter(function (c) {
      return c.id === id;
    })[0];
  }

  function nextComplaintId() {
    var max = 2400;
    getComplaints().forEach(function (c) {
      var n = parseInt(String(c.id).replace("CMP-", ""), 10);
      if (n > max) max = n;
    });
    return "CMP-" + (max + 1);
  }

  function getRequests() {
    return read(KEYS.requests, []);
  }

  function saveRequests(list) {
    write(KEYS.requests, list);
  }

  function param(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  var NAV = {
    user: [
      ["index.php", "Home"],
      ["my-complaints.html", "My complaints"],
      ["select-org.html", "File a complaint"],
    ],
    org: [
      ["index.php", "Home"],
      ["org-dashboard.html", "Dashboard"],
      ["org-settings.html", "Settings"],
    ],
    admin: [
      ["index.php", "Home"],
      ["admin-approvals.html", "Organization approvals"],
    ],
  };

  var ROLE_LABEL = { user: "Complainant", org: "Organization", admin: "Platform admin" };

  function mountShell(activeHref) {
    var s = session();
    var sidebar = document.getElementById("sidebar");
    if (!s || !sidebar) return;

    var links = (NAV[s.role] || [])
      .map(function (item) {
        var cls = item[0] === activeHref ? ' class="active"' : "";
        return '<a href="' + item[0] + '"' + cls + ">" + esc(item[1]) + "</a>";
      })
      .join("");

    sidebar.innerHTML =
      '<a class="brand-link" href="index.php">Complaint System</a>' +
      '<div class="role-tag">' + esc(ROLE_LABEL[s.role] || "") + "</div>" +
      "<nav>" + links + "</nav>" +
      '<div class="sidebar-foot"><a href="#" id="logoutLink">Log out</a></div>';

    document.getElementById("logoutLink").addEventListener("click", function (e) {
      e.preventDefault();
      logout();
    });

    var chip = document.getElementById("userChip");
    if (chip) chip.textContent = s.name || s.email;
  }

  seed();

  window.CMS = {
    read: read,
    write: write,
    esc: esc,
    fmtDate: fmtDate,
    fmtDateTime: fmtDateTime,
    statusBadge: statusBadge,
    priorityBadge: priorityBadge,
    STATUS_FLOW: STATUS_FLOW,
    PRIORITIES: PRIORITIES,
    DEFAULT_CATEGORIES: DEFAULT_CATEGORIES,
    KEYS: KEYS,
    session: session,
    setSession: setSession,
    logout: logout,
    homeFor: homeFor,
    requireRole: requireRole,
    getOrgs: getOrgs,
    findOrg: findOrg,
    getComplaints: getComplaints,
    saveComplaints: saveComplaints,
    findComplaint: findComplaint,
    nextComplaintId: nextComplaintId,
    getRequests: getRequests,
    saveRequests: saveRequests,
    param: param,
    mountShell: mountShell,
  };
}) ();