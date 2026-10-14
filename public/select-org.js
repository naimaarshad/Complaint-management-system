var session = CMS.requireRole(["user"]);

if (session) {
  var list = document.getElementById("orgList");
  var search = document.getElementById("orgSearch");

  function render() {
    var term = search.value.trim().toLowerCase();
    var orgs = CMS.getOrgs().filter(function (o) {
      return (o.name + " " + o.sector + " " + o.city).toLowerCase().indexOf(term) !== -1;
    });

    if (orgs.length === 0) {
      list.innerHTML = '<p class="sub">No organizations match your search.</p>';
      return;
    }

    list.innerHTML = orgs
      .map(function (o) {
        return (
          '<a class="org-card" href="file-complaint.html?org=' + encodeURIComponent(o.id) + '">' +
          '<div class="org-top"><span class="org-name">' + CMS.esc(o.name) + '</span><span class="org-arrow">→</span></div>' +
          '<div class="org-meta">' + CMS.esc(o.sector) + " · " + CMS.esc(o.city) + "</div>" +
          "</a>"
        );
      })
      .join("");
  }

  search.addEventListener("input", render);
  document.getElementById("logoutLink").addEventListener("click", function (e) {
    e.preventDefault();
    CMS.logout();
  });
  render();
}
