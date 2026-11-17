/*
 * Volunteer List (includes/volunteer-list.php): region and division filters,
 * and the inline signup panels. No-ops on pages without the list.
 *
 * Every row is in the HTML already, so with scripts off the whole list
 * shows and each Volunteer button is a plain link. Here the filter bars are
 * revealed, and a row's button opens a panel under the row that loads, on
 * first open only, RaceGoat's own volunteer widget for that race or an
 * iframe of the race's own iVolunteer event page.
 */
(function () {
  var EMBED = "https://racegoat.com/embed.js";
  var IVOL = "https://aravaiparunning.ivolunteer.com/";

  function loadWidget(panel) {
    if (panel.getAttribute("data-loaded")) return;
    panel.setAttribute("data-loaded", "1");
    var slot = panel.querySelector(".arv-vol__slot") || panel;
    // iVolunteer row: frame the race's own event page, made only now.
    var src = panel.getAttribute("data-arv-vol-frame");
    if (src) {
      // Only ever our own iVolunteer site: the src is rebuilt from the event
      // code, so nothing else from the attribute reaches the frame.
      var m = /^https:\/\/aravaiparunning\.ivolunteer\.com\/([A-Za-z0-9_-]+)\/?$/.exec(src);
      if (!m) return;
      var f = document.createElement("iframe");
      f.className = "arv-vol__frame";
      f.setAttribute("loading", "eager");
      f.setAttribute("title", panel.getAttribute("data-arv-vol-title") || "Volunteer signup");
      f.src = IVOL + encodeURIComponent(m[1]);
      slot.appendChild(f);
      return;
    }
    var tpl = panel.querySelector("template");
    if (tpl) slot.appendChild(tpl.content.cloneNode(true));
    // embed.js scans the page when it runs and skips widgets it already
    // drew, so running it again picks up only the one just added.
    var s = document.createElement("script");
    s.src = EMBED;
    s.async = true;
    document.body.appendChild(s);
  }

  function init(root) {
    var region = "", division = "";
    var rows = root.querySelectorAll(".arv-vol__row");
    var months = root.querySelectorAll(".arv-vol__month");
    var empty = root.querySelector(".arv-vol__empty");

    function apply() {
      var shown = 0;
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var ok = (!region || r.getAttribute("data-region") === region) && (!division || r.getAttribute("data-division") === division);
        r.hidden = !ok;
        if (ok) shown++;
      }
      for (var j = 0; j < months.length; j++) {
        months[j].hidden = !months[j].querySelector(".arv-vol__row:not([hidden])");
      }
      if (empty) empty.hidden = shown > 0;
    }

    function group(sel, attr, set) {
      var bar = root.querySelector(sel);
      if (!bar) return;
      bar.hidden = false;
      var btns = bar.querySelectorAll("button");
      for (var i = 0; i < btns.length; i++) {
        btns[i].addEventListener("click", function (e) {
          var b = e.currentTarget;
          for (var k = 0; k < btns.length; k++) btns[k].setAttribute("aria-pressed", btns[k] === b ? "true" : "false");
          set(b.getAttribute(attr) || "");
          apply();
        });
      }
    }
    group(".arv-vol__tabs", "data-division", function (v) { division = v; });
    group(".arv-vol__chips", "data-region", function (v) { region = v; });

    var opens = root.querySelectorAll("[data-arv-vol-open]");
    for (var i = 0; i < opens.length; i++) {
      opens[i].removeAttribute("target");
      opens[i].setAttribute("role", "button");
      opens[i].addEventListener("click", function (e) {
        var a = e.currentTarget;
        var panel = document.getElementById(a.getAttribute("data-arv-vol-open"));
        if (!panel) return;
        e.preventDefault();
        var open = panel.hidden;
        panel.hidden = !open;
        a.setAttribute("aria-expanded", open ? "true" : "false");
        a.textContent = open ? "Close" : "Volunteer";
        if (open) loadWidget(panel);
      });
    }
  }

  function run() {
    var lists = document.querySelectorAll("[data-arv-vol]");
    for (var i = 0; i < lists.length; i++) init(lists[i]);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run); else run();
})();
