var session = CMS.requireRole(["user"]);
var org = session ? CMS.findOrg(CMS.param("org")) : null;

if (session && !org) {
  window.location.replace("select-org.html");
} else if (session) {
  CMS.mountShell("select-org.html");

  document.getElementById("orgName").textContent = org.name;

  var categories = org.categories && org.categories.length ? org.categories : CMS.DEFAULT_CATEGORIES;
  document.getElementById("category").innerHTML =
    '<option value="">Choose a category</option>' +
    categories
      .map(function (c) {
        return "<option>" + CMS.esc(c) + "</option>";
      })
      .join("");

  var form = document.getElementById("complaintForm");
  var message = document.getElementById("formMessage");
  var attachment = document.getElementById("attachment");
  var attachLabel = document.getElementById("attachLabel");

  var fields = {
    category: document.getElementById("category"),
    subject: document.getElementById("subject"),
    description: document.getElementById("description"),
  };

  var rules = {
    category: function (v) {
      return v === "" ? "Choose a category." : "";
    },
    subject: function (v) {
      return v.trim().length < 5 ? "Write a short subject (at least 5 characters)." : "";
    },
    description: function (v) {
      return v.trim().length < 20 ? "Describe what happened (at least 20 characters)." : "";
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
    fields[name].addEventListener(name === "category" ? "change" : "blur", function () {
      validateField(name);
    });
  });

  attachment.addEventListener("change", function () {
    var file = attachment.files[0];
    attachLabel.firstChild.textContent = file ? "Attached: " + file.name : "Click to attach a photo or document";
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var allValid = Object.keys(fields).map(validateField).every(Boolean);
    if (!allValid) return;

    var now = new Date().toISOString();
    var complaint = {
      id: CMS.nextComplaintId(),
      orgId: org.id,
      orgName: org.name,
      userEmail: session.email,
      userName: session.name,
      category: fields.category.value,
      subject: fields.subject.value.trim(),
      description: fields.description.value.trim(),
      priority: "Medium",
      status: "Submitted",
      attachment: attachment.files[0] ? attachment.files[0].name : "",
      created: now,
      updates: [{ at: now, by: session.name, status: "Submitted", text: "Complaint submitted." }],
    };

    var all = CMS.getComplaints();
    all.push(complaint);
    CMS.saveComplaints(all);

    message.style.color = "";
    message.className = "form__message success";
    message.textContent = "Submitted. Opening your complaint…";
    form.querySelector(".btn-primary").disabled = true;

    window.location.href = "complaint-detail.html?id=" + encodeURIComponent(complaint.id) + "&new=1";
  });
}
