/**
 * Shanfix Technology — Main Application JavaScript
 */

// ─── Theme Management (Dark/Light Mode) ───────────────────────
(function () {
  const themeToggle = document.getElementById("themeToggle");
  const html = document.documentElement;
  const currentTheme = localStorage.getItem("theme") || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

  if (currentTheme === "dark") {
    html.classList.add("dark-mode");
  }

  themeToggle?.addEventListener("click", () => {
    html.classList.toggle("dark-mode");
    const theme = html.classList.contains("dark-mode") ? "dark" : "light";
    localStorage.setItem("theme", theme);
    
    // Smooth icon transition
    const icon = themeToggle.querySelector("i");
    if (icon) {
      icon.className = theme === "dark" ? "fa-solid fa-sun" : "fa-solid fa-moon";
    }
  });

  // Set initial icon
  if (themeToggle) {
    const icon = themeToggle.querySelector("i");
    if (icon) {
      icon.className = currentTheme === "dark" ? "fa-solid fa-sun" : "fa-solid fa-moon";
    }
  }
})();

// ─── Sidebar Toggle ───────────────────────────────────────────
(function () {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.getElementById("mainContent");
  const toggleBtn = document.getElementById("sidebarToggle");
  const toggleIcon = document.getElementById("toggleIcon");

  if (!sidebar || !toggleBtn) return;

  // Restore collapsed state
  if (localStorage.getItem("sidebarCollapsed") === "1") {
    sidebar.classList.add("collapsed");
    mainContent?.classList.add("sidebar-collapsed");
    toggleIcon && (toggleIcon.style.transform = "rotate(180deg)");
  }

  toggleBtn.addEventListener("click", () => {
    sidebar.classList.toggle("collapsed");
    mainContent?.classList.toggle("sidebar-collapsed");
    const collapsed = sidebar.classList.contains("collapsed");
    localStorage.setItem("sidebarCollapsed", collapsed ? "1" : "0");
    if (toggleIcon)
      toggleIcon.style.transform = collapsed ? "rotate(180deg)" : "";
  });

  // Mobile menu
  const mobileBtn = document.getElementById("mobileMenuBtn");
  mobileBtn?.addEventListener("click", () =>
    sidebar.classList.toggle("mobile-open"),
  );
  document.addEventListener("click", (e) => {
    if (
      window.innerWidth < 768 &&
      !sidebar.contains(e.target) &&
      !mobileBtn?.contains(e.target)
    ) {
      sidebar.classList.remove("mobile-open");
    }
  });
})();

// ─── Nav Submenu Toggle ───────────────────────────────────────
document.querySelectorAll(".nav-link[data-toggle]").forEach((link) => {
  link.addEventListener("click", (e) => {
    e.preventDefault();
    const id = link.dataset.toggle;
    const sub = document.getElementById("sub-" + id);
    if (!sub) return;
    const isOpen = sub.classList.contains("open");
    // Close others
    document
      .querySelectorAll(".nav-sub.open")
      .forEach((el) => el.classList.remove("open"));
    document
      .querySelectorAll('.nav-link[aria-expanded="true"]')
      .forEach((el) => el.setAttribute("aria-expanded", "false"));
    if (!isOpen) {
      sub.classList.add("open");
      link.setAttribute("aria-expanded", "true");
    }
  });
});

// ─── Modal ────────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add("open");
    document.body.style.overflow = "hidden";
  }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove("open");
    document.body.style.overflow = "";
  }
}
// Close on overlay click
document.querySelectorAll(".modal-overlay").forEach((overlay) => {
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal(overlay.id);
  });
});
// Close on Escape
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document
      .querySelectorAll(".modal-overlay.open")
      .forEach((el) => closeModal(el.id));
  }
});

// ─── Dropdown Toggle ──────────────────────────────────────────
function toggleDropdown(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const isOpen = el.classList.contains("open");
  document
    .querySelectorAll(".dropdown.open")
    .forEach((d) => d.classList.remove("open"));
  if (!isOpen) el.classList.add("open");
}
document.addEventListener("click", (e) => {
  if (!e.target.closest(".dropdown")) {
    document
      .querySelectorAll(".dropdown.open")
      .forEach((d) => d.classList.remove("open"));
  }
});

// ─── Tab Switcher ─────────────────────────────────────────────
function switchTab(btn, panelId) {
  const container =
    btn.closest(".form-group") || btn.parentElement.parentElement;
  container
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.remove("active"));
  container
    .querySelectorAll(".tab-panel")
    .forEach((p) => p.classList.remove("active"));
  btn.classList.add("active");
  const panel = document.getElementById(panelId);
  if (panel) panel.classList.add("active");
}

// ─── SMS Character Counter ────────────────────────────────────
document.querySelectorAll("textarea[data-sms-counter]").forEach((ta) => {
  const counterId = ta.dataset.smsCounter;
  const counter = document.getElementById(counterId);
  if (!counter) return;
  ta.addEventListener("input", () => {
    const l = ta.value.length;
    const segs = Math.ceil(l / 160) || 1;
    counter.textContent = `${l}/160 · ${segs} SMS`;
  });
});

// ─── Flash Message Auto-dismiss ───────────────────────────────
document.querySelectorAll(".alert").forEach((alert) => {
  setTimeout(() => {
    alert.style.transition = "opacity 0.5s";
    alert.style.opacity = "0";
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

// ─── Upload Zone Drag & Drop ──────────────────────────────────
document.querySelectorAll(".upload-zone").forEach((zone) => {
  zone.addEventListener("dragover", (e) => {
    e.preventDefault();
    zone.classList.add("dragging");
  });
  zone.addEventListener("dragleave", () => zone.classList.remove("dragging"));
  zone.addEventListener("drop", (e) => {
    e.preventDefault();
    zone.classList.remove("dragging");
    const files = e.dataTransfer.files;
    const input = zone.parentElement.querySelector("input[type=file]");
    if (input && files.length) {
      input.files = files;
      zone.querySelector("p").textContent = "File: " + files[0].name;
    }
  });
});

// ─── Global Search ────────────────────────────────────────────
const globalSearch = document.getElementById("globalSearch");
if (globalSearch) {
  globalSearch.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      const q = globalSearch.value.trim();
      if (!q) return;
      
      const role = document.body.dataset.role || 'client';
      const currentPath = window.location.pathname;
      let targetPath = currentPath;

      // Intelligent routing based on role if on a non-searchable page (like Dashboard)
      if (currentPath.includes('index.php') || currentPath.endsWith('/')) {
        if (role === 'admin') {
          targetPath = '/admin/resellers.php';
        } else if (role === 'reseller') {
          targetPath = '/reseller/clients.php';
        } else {
          targetPath = '/client/contacts.php';
        }
      }

      window.location.href = targetPath + "?q=" + encodeURIComponent(q);
    }
  });
}

// ─── Refresh Units Badge ──────────────────────────────────────
document.getElementById("refreshUnits")?.addEventListener("click", async () => {
  try {
    const res = await fetch("/includes/actions/api.php?action=get_units");
    const data = await res.json();
    if (data.units !== undefined) {
      document.querySelector(".units-value").textContent = parseFloat(
        data.units,
      ).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    }
  } catch (e) {
    /* silent */
  }
});

// ─── AJAX Navigation (Zero-Blink Experience) ──────────────────
(function () {
  const loader = document.getElementById("page-loader");
  const mainContent = document.getElementById("mainContent");

  // Only run if we are in a portal context
  if (!mainContent) return;

  async function navigate(url, push = true) {
    // Don't AJAX external or specific links
    if (url.includes("logout.php") || url.includes("download")) {
      window.location.href = url;
      return;
    }

    loader?.classList.add("loading");

    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error("Network response was not ok");
      const html = await response.text();

      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      const newContent = doc.querySelector(".page-content");
      const newTitle = doc.querySelector("title");
      const currentContent = document.querySelector(".page-content");

      if (newContent && currentContent) {
        const updateDOM = () => {
          currentContent.innerHTML = newContent.innerHTML;
          if (newTitle) document.title = newTitle.innerText;
          if (push) window.history.pushState({ url }, "", url);

          // Update active states in sidebar
          document.querySelectorAll(".sidebar .nav-link").forEach((link) => {
            const linkUrl = link.getAttribute("href");
            if (linkUrl && url.includes(linkUrl) && linkUrl !== "#" && linkUrl !== "/") {
              link.classList.add("active");
              // Expand parent if it's a sub-item
              const sub = link.closest(".nav-sub");
              if (sub) {
                sub.classList.add("open");
                const parentLink = document.querySelector(`[data-toggle="${sub.id.replace('sub-', '')}"]`);
                parentLink?.classList.add("active");
                parentLink?.setAttribute("aria-expanded", "true");
              }
            } else {
              link.classList.remove("active");
            }
          });

          // Re-initialize page-specific features
          initPageFeatures();
          
          // CRITICAL: Execute scripts found in the new document
          executeScripts(doc);

          window.scrollTo(0, 0);
        };

        // Use native View Transition if supported
        if (document.startViewTransition) {
          document.startViewTransition(updateDOM);
        } else {
          updateDOM();
        }
      } else {
        window.location.href = url; // Fallback
      }
    } catch (error) {
      console.error("Navigation error:", error);
      window.location.href = url; // Fallback
    } finally {
      setTimeout(() => loader?.classList.remove("loading"), 300);
    }
  }

  function executeScripts(doc) {
    // Extract scripts that are not app.js
    const scripts = Array.from(doc.querySelectorAll("script")).filter(s => {
        const src = s.getAttribute("src");
        return !src || (!src.includes("app.js") && !src.includes("font-awesome") && !src.includes("sweetalert2"));
    });

    scripts.forEach(oldScript => {
        const newScript = document.createElement("script");
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        document.body.appendChild(newScript);
        // Remove it immediately to keep DOM clean (it already executed)
        if (!newScript.src) newScript.remove();
    });
  }

  function initPageFeatures() {
    // 1. Re-init Charts if any
    document.querySelectorAll("canvas[id]").forEach(canvas => {
       // Note: In a real app, you'd need the specific chart data.
       // For now, we assume pages handle their own chart init via embedded scripts.
       // However, scripts in innerHTML don't run automatically.
       // We'll extract and run them.
    });

    // 2. Re-init SMS Counters
    document.querySelectorAll("textarea[data-sms-counter]").forEach((ta) => {
      const counterId = ta.dataset.smsCounter;
      const counter = document.getElementById(counterId);
      if (!counter) return;
      ta.addEventListener("input", () => {
        const l = ta.value.length;
        const segs = Math.ceil(l / 160) || 1;
        counter.textContent = `${l}/160 · ${segs} SMS`;
      });
    });

    // 3. Re-init Upload Zones
    document.querySelectorAll(".upload-zone").forEach((zone) => {
      zone.addEventListener("dragover", (e) => { e.preventDefault(); zone.classList.add("dragging"); });
      zone.addEventListener("dragleave", () => zone.classList.remove("dragging"));
      zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("dragging");
        const files = e.dataTransfer.files;
        const input = zone.parentElement.querySelector("input[type=file]");
        if (input && files.length) { input.files = files; zone.querySelector("p").textContent = "File: " + files[0].name; }
      });
    });

    // 4. Trigger "animate-in" again
    const content = document.querySelector(".page-content");
    if (content) {
      content.classList.remove("animate-in");
      void content.offsetWidth; // Trigger reflow
      content.classList.add("animate-in");
    }
  }

  // Intercept all internal link clicks
  document.addEventListener("click", (e) => {
    const link = e.target.closest("a");
    if (!link) return;

    const url = link.getAttribute("href");
    if (!url || url.startsWith("#") || url.startsWith("javascript:") || link.target === "_blank") return;

    // Only AJAX for same-origin portal pages
    if (url.startsWith("/") || url.startsWith(window.location.origin)) {
      e.preventDefault();
      navigate(url);
    }
  });

  // Handle browser back/forward
  window.addEventListener("popstate", (e) => {
    if (e.state && e.state.url) {
      navigate(e.state.url, false);
    } else {
      window.location.reload();
    }
  });
})();

// ─── Confirm forms with data-confirm ─────────────────────────
document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (e) => {
    if (!confirm(form.dataset.confirm)) e.preventDefault();
  });
});
