var session = CMS.requireRole(["org"]);

if (session && !session.orgId) {
  window.location.replace("org-pending.html");
} else if (session) {
  CMS.mountShell("org-settings.html");

  var MAX_CATEGORIES = 12;
  var catList = document.getElementById("catList");
  var catForm = document.getElementById("catForm");
  var catName = document.getElementById("catName");
  var catError = document.getElementById("catNameError");
  var catMessage = document.getElementById("catMessage");

  function currentOrg() {
    return CMS.findOrg(session.orgId);
  }

  function categoriesOf(org) {
    return org.categories && org.categories.length ? org.categories.slice() : CMS.DEFAULT_CATEGORIES.slice();
  }

  function saveCategories(list) {
    var orgs = CMS.getOrgs().map(function (o) {
      if (o.id === session.orgId) o.categories = list;
      return o;
    });
    CMS.write(CMS.KEYS.orgs, orgs);
  }

  function renderProfile() {
    var org = currentOrg();
    if (!org) return;
    var rows = [
      ["Name", org.name],
      ["Sector", org.sector],
      ["City", org.city],
    ];
    document.getElementById("profile").innerHTML = rows
      .map(function (r) {
        return "<div><dt>" + r[0] + "</dt><dd>" + CMS.esc(r[1]) + "</dd></div>";
      })
      .join("");
  }

  function renderCategories() {
    var org = currentOrg();
    if (!org) return;
    var list = categoriesOf(org);

    catList.innerHTML = list
      .map(function (c, i) {
        return (
          '<li class="cat-item"><span>' + CMS.esc(c) + "</span>" +
          '<button type="button" class="cat-remove" data-index="' + i + '" aria-label="Remove ' + CMS.esc(c) + '"' +
          (list.length === 1 ? " disabled" : "") + ">Remove</button></li>"
        );
      })
      .join("");
  }

  function say(text, ok) {
    catMessage.className = "form__message" + (ok ? " success" : "");
    catMessage.style.color = ok ? "" : "#b3261e";
    catMessage.textContent = text;
  }

  catList.addEventListener("click", function (e) {
    var button = e.target.closest("button[data-index]");
    if (!button) return;
    var list = categoriesOf(currentOrg());
    if (list.length <= 1) return;
    var removed = list.splice(parseInt(button.getAttribute("data-index"), 10), 1)[0];
    saveCategories(list);
    renderCategories();
    say('Removed "' + removed + '".', true);
  });

  catForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var name = catName.value.trim();
    var list = categoriesOf(currentOrg());
    var error = "";

    if (name.length < 2) error = "Enter a category name (at least 2 characters).";
    else if (
      list.some(function (c) {
        return c.toLowerCase() === name.toLowerCase();
      })
    )
      error = "That category already exists.";
    else if (list.length >= MAX_CATEGORIES) error = "You can have up to " + MAX_CATEGORIES + " categories.";

    catName.closest(".field").classList.toggle("invalid", error !== "");
    catError.textContent = error;
    if (error) return;

    list.push(name);
    saveCategories(list);
    catName.value = "";
    renderCategories();
    say('Added "' + name + '". It now appears on your complaint form.', true);
  });

  renderProfile();
  renderCategories();
}
