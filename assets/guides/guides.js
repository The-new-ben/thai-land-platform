(function () {
  "use strict";

  var documentElement = document.documentElement;
  var body = document.body;
  var header = document.querySelector("[data-thp-guide-header]");
  var openButton = document.querySelector("[data-thp-menu-open]");
  var shell = document.querySelector("[data-thp-mobile-shell]");
  var panel = document.querySelector("[data-thp-mobile-panel]");
  var closeControls = document.querySelectorAll("[data-thp-menu-close]");
  var previousFocus = null;
  var focusableSelector = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    "[tabindex]:not([tabindex='-1'])"
  ].join(",");

  function focusableItems() {
    if (!panel) {
      return [];
    }
    return Array.prototype.filter.call(
      panel.querySelectorAll(focusableSelector),
      function (item) {
        return !item.hasAttribute("hidden") && item.getAttribute("aria-hidden") !== "true";
      }
    );
  }

  function openMenu() {
    if (!shell || !panel || !openButton) {
      return;
    }
    previousFocus = document.activeElement;
    shell.removeAttribute("hidden");
    openButton.setAttribute("aria-expanded", "true");
    body.classList.add("thp-guide-menu-open");
    panel.focus();
  }

  function closeMenu(restoreFocus) {
    if (!shell || !openButton) {
      return;
    }
    shell.setAttribute("hidden", "hidden");
    openButton.setAttribute("aria-expanded", "false");
    body.classList.remove("thp-guide-menu-open");
    if (restoreFocus !== false && previousFocus && typeof previousFocus.focus === "function") {
      previousFocus.focus();
    }
  }

  function keepFocusInside(event) {
    if (event.key !== "Tab" || !shell || shell.hasAttribute("hidden")) {
      return;
    }
    var items = focusableItems();
    if (!items.length) {
      event.preventDefault();
      panel.focus();
      return;
    }
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  if (openButton && shell && panel) {
    openButton.removeAttribute("hidden");
    openButton.addEventListener("click", openMenu);
    Array.prototype.forEach.call(closeControls, function (control) {
      control.addEventListener("click", closeMenu);
    });
    panel.addEventListener("keydown", keepFocusInside);
    panel.addEventListener("click", function (event) {
      if (event.target.closest("a[href]")) {
        closeMenu();
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !shell.hasAttribute("hidden")) {
        closeMenu();
      }
    });
    window.addEventListener("resize", function () {
      if (window.matchMedia("(min-width: 981px)").matches && !shell.hasAttribute("hidden")) {
        var focusWasInside = panel.contains(document.activeElement);
        closeMenu(false);
        var desktopFocusTarget = document.querySelector(".thp-guide-brand");
        if (focusWasInside && desktopFocusTarget) {
          desktopFocusTarget.focus({ preventScroll: true });
        }
      }
    });
  }

  var tocLinks = Array.prototype.slice.call(
    document.querySelectorAll(".thp-guide-toc a[href^='#']")
  );
  var sections = Array.prototype.slice.call(
    document.querySelectorAll("[data-thp-guide-section]")
  );

  function setActiveSection(sectionId) {
    tocLinks.forEach(function (link) {
      var active = link.getAttribute("href") === "#" + sectionId;
      link.classList.toggle("is-active", active);
      if (active) {
        link.setAttribute("aria-current", "location");
      } else {
        link.removeAttribute("aria-current");
      }
    });
  }

  if ("IntersectionObserver" in window && sections.length && tocLinks.length) {
    var sectionObserver = new IntersectionObserver(
      function (entries) {
        var visible = entries
          .filter(function (entry) { return entry.isIntersecting; })
          .sort(function (left, right) { return left.boundingClientRect.top - right.boundingClientRect.top; });
        if (visible.length) {
          setActiveSection(visible[0].target.id);
        }
      },
      { rootMargin: "-18% 0px -66% 0px", threshold: [0, 0.1, 0.5] }
    );
    sections.forEach(function (section) {
      sectionObserver.observe(section);
    });
  }

  tocLinks.forEach(function (link) {
    link.addEventListener("click", function (event) {
      var target = document.querySelector(link.getAttribute("href"));
      if (!target) {
        return;
      }
      event.preventDefault();
      var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      target.scrollIntoView({ behavior: reducedMotion ? "auto" : "smooth", block: "start" });
      window.history.replaceState(null, "", "#" + target.id);
    });
  });

  if (header) {
    var ticking = false;
    window.addEventListener("scroll", function () {
      if (ticking) {
        return;
      }
      ticking = true;
      window.requestAnimationFrame(function () {
        header.classList.toggle("is-scrolled", window.scrollY > 20);
        ticking = false;
      });
    }, { passive: true });
  }

  documentElement.classList.add("thp-guides-enhanced");
}());
