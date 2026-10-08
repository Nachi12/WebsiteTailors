/**
 * Website Tailors — Admin Panel Interactions
 * Pure Vanilla JavaScript
 */

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  const sidebar = document.getElementById("adminSidebar");
  const backdrop = document.getElementById("sidebarBackdrop");
  const toggleBtn = document.getElementById("sidebarToggle");
  const closeBtn = document.getElementById("sidebarClose");

  function isMobileView() {
    return window.innerWidth <= 992;
  }

  // 1. Mobile Off-Canvas Sidebar Controls
  function openMobileSidebar() {
    if (sidebar && backdrop) {
      sidebar.classList.add("mobile-open");
      backdrop.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  }

  function closeMobileSidebar() {
    if (sidebar && backdrop) {
      sidebar.classList.remove("mobile-open");
      backdrop.classList.remove("active");
      document.body.style.overflow = "";
    }
  }

  // 2. Universal Sidebar Toggle (Desktop Collapse / Mobile Drawer)
  function toggleSidebar() {
    if (!sidebar) return;
    if (isMobileView()) {
      if (sidebar.classList.contains("mobile-open")) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }
    } else {
      const isCollapsed = sidebar.classList.toggle("collapsed");
      if (isCollapsed) {
        document.documentElement.classList.add("sidebar-is-collapsed");
        try { localStorage.setItem("admin_sidebar_collapsed", "true"); } catch (e) {}
      } else {
        document.documentElement.classList.remove("sidebar-is-collapsed");
        try { localStorage.setItem("admin_sidebar_collapsed", "false"); } catch (e) {}
      }
    }
  }

  // Initialize desktop state from localStorage
  if (sidebar && !isMobileView()) {
    try {
      if (localStorage.getItem("admin_sidebar_collapsed") === "true") {
        sidebar.classList.add("collapsed");
        document.documentElement.classList.add("sidebar-is-collapsed");
      }
    } catch (e) {}
  }

  if (toggleBtn) {
    toggleBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleSidebar();
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      closeMobileSidebar();
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", (e) => {
      e.preventDefault();
      closeMobileSidebar();
    });
  }

  // Close mobile sidebar on Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isMobileView() && sidebar && sidebar.classList.contains("mobile-open")) {
      closeMobileSidebar();
    }
  });

  // Close mobile sidebar when clicking any navigation link
  if (sidebar) {
    sidebar.querySelectorAll(".sidebar-link").forEach((link) => {
      link.addEventListener("click", () => {
        if (isMobileView()) {
          closeMobileSidebar();
        }
      });
    });
  }

  // Responsive resize handler
  window.addEventListener("resize", () => {
    if (!isMobileView()) {
      closeMobileSidebar();
      try {
        if (localStorage.getItem("admin_sidebar_collapsed") === "true") {
          sidebar.classList.add("collapsed");
          document.documentElement.classList.add("sidebar-is-collapsed");
        } else {
          sidebar.classList.remove("collapsed");
          document.documentElement.classList.remove("sidebar-is-collapsed");
        }
      } catch (e) {}
    } else {
      // Clear desktop collapsed classes in mobile view so drawer renders full width
      sidebar.classList.remove("collapsed");
      document.documentElement.classList.remove("sidebar-is-collapsed");
    }
  });

  // 2. Alert Dismissal
  document.querySelectorAll(".alert-dismiss-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const alertBox = this.closest(".admin-alert");
      if (alertBox) {
        alertBox.style.opacity = "0";
        setTimeout(() => alertBox.remove(), 250);
      }
    });
  });

  // 3. Simple Bar Chart Hover Tooltips (Vanilla JS)
  const barCols = document.querySelectorAll(".bar-col");
  barCols.forEach((col) => {
    col.addEventListener("mouseenter", function () {
      const val = this.getAttribute("data-val");
      const label = this.getAttribute("data-label");
      if (val) {
        this.setAttribute("title", `${label}: ${val} inquiries`);
      }
    });
  });

  // 4. Live Image Upload Previews
  document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
    input.addEventListener("change", function () {
      const previewSelector = this.getAttribute("data-preview");
      const previewBox = document.querySelector(previewSelector);
      if (!previewBox) return;

      const file = this.files && this.files[0];
      if (file) {
        // Validate file type
        const allowed = ["image/jpeg", "image/png", "image/webp", "image/jpg"];
        if (!allowed.includes(file.type)) {
          window.AdminToast.show("Please select a valid image (JPG, PNG, or WebP).", "error");
          this.value = "";
          return;
        }

        // Validate max 8MB
        if (file.size > 8 * 1024 * 1024) {
          window.AdminToast.show("File size exceeds 8MB limit.", "error");
          this.value = "";
          return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
          previewBox.classList.add("has-image");
          previewBox.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;" />`;
        };
        reader.readAsDataURL(file);
      }
    });
  });

  // 5. Submit Button Loading States
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", function () {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.classList.contains("no-loader")) {
        submitBtn.classList.add("btn-loading");
      }
    });
  });

  // 6. Global Confirmation Interceptor (Replaces browser confirm/alert)
  document.addEventListener("click", function (e) {
    const target = e.target.closest("[data-confirm]");
    if (!target) return;

    e.preventDefault();
    e.stopPropagation();

    const message = target.getAttribute("data-confirm") || "Are you sure you want to proceed?";
    const title = target.getAttribute("data-confirm-title") || "Confirm Action";
    const confirmText = target.getAttribute("data-confirm-btn") || "Yes, Proceed";
    const isDanger = target.getAttribute("data-confirm-danger") !== "false";

    window.AdminModal.confirm({
      title: title,
      message: message,
      confirmText: confirmText,
      isDanger: isDanger,
      onConfirm: () => {
        if (target.tagName.toLowerCase() === "a") {
          window.location.href = target.href;
        } else if (target.tagName.toLowerCase() === "button" && target.type === "submit") {
          const form = target.closest("form");
          if (form) {
            // Append button value if any
            if (target.name) {
              const hidden = document.createElement("input");
              hidden.type = "hidden";
              hidden.name = target.name;
              hidden.value = target.value;
              form.appendChild(hidden);
            }
            form.submit();
          }
        }
      }
    });
  });
});

/**
 * Global Admin Toast Component
 */
window.AdminToast = {
  show(message, type = "success", duration = 3500) {
    let container = document.getElementById("adminToastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "adminToastContainer";
      container.className = "admin-toast-container";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = `admin-toast ${type}`;

    const iconSvg = type === "error" 
      ? '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#ef4444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
      : '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#10b981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';

    toast.innerHTML = `
      <div class="toast-content">
        ${iconSvg}
        <span>${message}</span>
      </div>
      <button class="toast-close" aria-label="Close notification">&times;</button>
    `;

    container.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.add("visible"), 20);

    const removeToast = () => {
      toast.classList.remove("visible");
      setTimeout(() => toast.remove(), 300);
    };

    toast.querySelector(".toast-close").addEventListener("click", removeToast);
    if (duration > 0) {
      setTimeout(removeToast, duration);
    }
  }
};

/**
 * Global Custom Modal Component (Replaces browser alert/confirm)
 */
window.AdminModal = {
  confirm({
    title = "Confirm Action",
    message = "Are you sure you want to perform this action?",
    confirmText = "Confirm",
    cancelText = "Cancel",
    isDanger = true,
    onConfirm = () => {}
  }) {
    let backdrop = document.getElementById("adminModalBackdrop");
    if (!backdrop) {
      backdrop = document.createElement("div");
      backdrop.id = "adminModalBackdrop";
      backdrop.className = "admin-modal-backdrop";
      backdrop.innerHTML = `
        <div class="admin-modal-box" role="dialog" aria-modal="true">
          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon ${isDanger ? 'danger' : 'info'}" id="modalIcon">
                ${isDanger 
                  ? '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>' 
                  : '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                }
              </div>
              <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
            </div>
            <button class="modal-close-btn" id="modalCloseBtn" aria-label="Close dialog">&times;</button>
          </div>
          <div class="modal-body" id="modalBody">Are you sure?</div>
          <div class="modal-footer">
            <button class="btn-modal-cancel" id="modalCancelBtn">Cancel</button>
            <button class="btn-modal-confirm ${isDanger ? 'danger' : 'primary'}" id="modalConfirmBtn">Confirm</button>
          </div>
        </div>
      `;
      document.body.appendChild(backdrop);
    }

    const titleEl = backdrop.querySelector("#modalTitle");
    const bodyEl = backdrop.querySelector("#modalBody");
    const confirmBtn = backdrop.querySelector("#modalConfirmBtn");
    const cancelBtn = backdrop.querySelector("#modalCancelBtn");
    const closeBtn = backdrop.querySelector("#modalCloseBtn");
    const iconEl = backdrop.querySelector("#modalIcon");

    titleEl.textContent = title;
    bodyEl.textContent = message;
    confirmBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;

    confirmBtn.className = `btn-modal-confirm ${isDanger ? 'danger' : 'primary'}`;
    iconEl.className = `modal-icon ${isDanger ? 'danger' : 'info'}`;

    const closeModal = () => {
      backdrop.classList.remove("active");
    };

    closeBtn.onclick = closeModal;
    cancelBtn.onclick = closeModal;
    backdrop.onclick = (e) => {
      if (e.target === backdrop) closeModal();
    };

    confirmBtn.onclick = () => {
      closeModal();
      if (typeof onConfirm === "function") {
        onConfirm();
      }
    };

    backdrop.classList.add("active");
  },

  alert({
    title = "Notice",
    message = "",
    buttonText = "OK"
  }) {
    this.confirm({
      title: title,
      message: message,
      confirmText: buttonText,
      cancelText: "",
      isDanger: false
    });
    const cancelBtn = document.getElementById("modalCancelBtn");
    if (cancelBtn) cancelBtn.style.display = "none";
  }
};

/**
 * Global CRM Search Controller
 */
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("globalCrmSearchInput");
  const dropdown = document.getElementById("globalSearchDropdown");
  if (!searchInput || !dropdown) return;

  // Keyboard shortcut '/' or 'Cmd+K' / 'Ctrl+K'
  document.addEventListener("keydown", (e) => {
    if (e.key === "/" && document.activeElement.tagName !== "INPUT" && document.activeElement.tagName !== "TEXTAREA") {
      e.preventDefault();
      searchInput.focus();
    } else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      searchInput.focus();
    } else if (e.key === "Escape") {
      dropdown.style.display = "none";
    }
  });

  let debounceTimer = null;

  searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    const query = searchInput.value.trim();

    if (query.length < 2) {
      dropdown.style.display = "none";
      dropdown.innerHTML = "";
      return;
    }

    debounceTimer = setTimeout(() => {
      // Determine base URL for admin
      const isPagesDir = window.location.pathname.includes("/pages/");
      const endpoint = isPagesDir ? "../ajax_search.php" : "ajax_search.php";

      fetch(`${endpoint}?q=${encodeURIComponent(query)}`)
        .then((res) => res.json())
        .then((data) => {
          if (!data || !data.success) {
            dropdown.style.display = "none";
            return;
          }

          if (data.total === 0) {
            dropdown.innerHTML = `
              <div style="padding: 12px 16px; font-size: 12px; color: var(--text-muted); text-align: center;">
                No clients, leads, or companies matching "<strong>${escapeHtml(query)}</strong>"
              </div>
            `;
            dropdown.style.display = "block";
            return;
          }

          let html = '';

          // Clients Group
          if (data.clients && data.clients.length > 0) {
            html += `
              <div style="padding: 6px 14px; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; border-bottom: 1px solid var(--border-light);">
                Clients (${data.clients.length})
              </div>
            `;
            data.clients.forEach((c) => {
              const clientsUrl = isPagesDir ? "clients.php" : "clients.php";
              html += `
                <a href="${clientsUrl}?q=${encodeURIComponent(c.phone || c.client_name)}" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; text-decoration: none; color: inherit; border-bottom: 1px solid var(--border-light); transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                  <div>
                    <strong style="font-size: 13px; color: var(--text-dark);">${escapeHtml(c.client_name)}</strong>
                    ${c.company_name ? `<span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">(${escapeHtml(c.company_name)})</span>` : ''}
                    <div style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                      ${escapeHtml(c.phone || c.email || 'No phone')}
                    </div>
                  </div>
                  <span style="font-size: 11px; background: var(--main-bg); padding: 2px 8px; border-radius: 4px; font-family: 'DM Mono', monospace;">
                    Client
                  </span>
                </a>
              `;
            });
          }

          // Leads Group
          if (data.leads && data.leads.length > 0) {
            html += `
              <div style="padding: 6px 14px; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; border-bottom: 1px solid var(--border-light); margin-top: 4px;">
                Leads (${data.leads.length})
              </div>
            `;
            data.leads.forEach((l) => {
              const leadsUrl = isPagesDir ? "leads.php" : "leads.php";
              html += `
                <a href="${leadsUrl}?q=${encodeURIComponent(l.phone || l.name)}" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; text-decoration: none; color: inherit; border-bottom: 1px solid var(--border-light); transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                  <div>
                    <strong style="font-size: 13px; color: var(--text-dark);">${escapeHtml(l.name)}</strong>
                    ${l.company ? `<span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">(${escapeHtml(l.company)})</span>` : ''}
                    <div style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                      ${escapeHtml(l.phone || l.email || 'No phone')}
                    </div>
                  </div>
                  <span style="font-size: 11px; background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 4px; font-family: 'DM Mono', monospace;">
                    ${escapeHtml(l.status || 'Lead')}
                  </span>
                </a>
              `;
            });
          }

          dropdown.innerHTML = html;
          dropdown.style.display = "block";
        })
        .catch((err) => {
          console.error("Global search error:", err);
          dropdown.style.display = "none";
        });
    }, 200);
  });

  searchInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      const q = searchInput.value.trim();
      if (q) {
        const isPagesDir = window.location.pathname.includes("/pages/");
        window.location.href = (isPagesDir ? "clients.php" : "clients.php") + "?q=" + encodeURIComponent(q);
      }
    }
  });

  // Hide dropdown on click outside
  document.addEventListener("click", (e) => {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = "none";
    }
  });

  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
});

