document.addEventListener("DOMContentLoaded", () => {
    const links = document.querySelectorAll("ul li a");
    const search = document.getElementById("routeSearch");
    const stats = document.getElementById("routeStats");
    const headers = document.querySelectorAll("h2");

    /* === Aktif route === */
    const currentPath = location.pathname;
    links.forEach(link => {
        if (link.getAttribute("href") === currentPath) {
            link.classList.add("active");
        }
    });

    /* === Badge ekleme === */
    links.forEach(link => {
        const href = link.getAttribute("href");
        const badge = document.createElement("span");
        badge.classList.add("route-badge");

        if (href.startsWith("/admin")) {
            badge.textContent = "ADMIN";
            badge.classList.add("badge-admin");
        } else if (href.includes("delete") || href.includes("destroy")) {
            badge.textContent = "DANGER";
            badge.classList.add("badge-danger");
        } else {
            badge.textContent = "GET";
        }

        link.appendChild(badge);
    });

    /* === Delete onayı === */
    links.forEach(link => {
        const href = link.getAttribute("href");
        if (href.includes("delete") || href.includes("destroy")) {
            link.addEventListener("click", e => {
                if (!confirm("⚠️ Bu işlem geri alınamaz. Devam edilsin mi?")) {
                    e.preventDefault();
                }
            });
        }
    });

    /* === Route kopyalama === */
    links.forEach(link => {
        link.addEventListener("dblclick", e => {
            e.preventDefault();
            navigator.clipboard.writeText(link.href);
            link.textContent = "✔ Kopyalandı";
            setTimeout(() => location.reload(), 500);
        });
    });

    /* === Search === */
    search.addEventListener("input", () => {
        const value = search.value.toLowerCase();
        links.forEach(link => {
            const match = link.getAttribute("href").toLowerCase().includes(value);
            link.parentElement.style.display = match ? "block" : "none";
        });
    });

    /* === Collapse === */
    headers.forEach(header => {
        const list = header.nextElementSibling;
        header.addEventListener("click", () => {
            list.style.display = list.style.display === "none" ? "grid" : "none";
            header.classList.toggle("collapsed");
        });
    });

    /* === Stats === */
    stats.textContent = `
        Toplam Route: ${links.length} |
        Admin: ${[...links].filter(l => l.href.includes("/admin")).length} |
        Riskli: ${[...links].filter(l => l.href.includes("delete") || l.href.includes("destroy")).length}
    `;
});

const response = await fetch('/api/users/' + id);
const data = await response.json();
const user = data.user;
const name = user.name;
const email = user.email;
const password = user.password;
const createdAt = user.createdAt;