const configUrl = "/plugins/flex/shipping-bar/config";
const cartUrl = "/plugins/flex/commerce/cart/data";

export function subtotalFor(cart, currency) {
  const totals = Array.isArray(cart?.totals) ? cart.totals : [];
  const match = totals.find((row) => row && row.currency === currency);
  const minor = Number(match?.amount_minor);
  return Number.isFinite(minor) && minor > 0 ? Math.round(minor) : 0;
}

export function barView(config, cart) {
  const threshold = Number(config?.threshold_minor) || 0;
  const currency = config?.currency || "EUR";
  const subtotal = subtotalFor(cart, currency);
  const remaining = Math.max(0, threshold - subtotal);
  const amounts = {
    threshold: money(threshold, currency),
    remaining: money(remaining, currency),
    subtotal: money(subtotal, currency),
  };
  let state = "empty";
  let template = config?.message_empty || "";
  if (subtotal > 0 && threshold > 0 && subtotal < threshold) {
    state = "progress";
    template = config?.message_progress || "";
  } else if (threshold > 0 && subtotal >= threshold) {
    state = "reached";
    template = config?.message_reached || "";
  }

  return {
    state,
    text: fill(template, amounts),
    progress: threshold > 0 ? Math.max(0, Math.min(1, subtotal / threshold)) : 0,
  };
}

function money(minor, currency) {
  try {
    return new Intl.NumberFormat("bg-BG", {
      style: "currency",
      currency,
    }).format(minor / 100);
  } catch {
    return (minor / 100).toFixed(2) + " " + currency;
  }
}

function fill(template, amounts) {
  return String(template)
    .replaceAll("{threshold}", amounts.threshold)
    .replaceAll("{remaining}", amounts.remaining)
    .replaceAll("{subtotal}", amounts.subtotal);
}

function hex(value) {
  return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value) ? value : "";
}

function paint(bar, config, cart) {
  const view = barView(config, cart);
  bar.classList.toggle("is-empty", view.state === "empty");
  bar.classList.toggle("is-progress", view.state === "progress");
  bar.classList.toggle("is-reached", view.state === "reached");
  const text = bar.querySelector(".shipping-bar-text");
  const meter = bar.querySelector(".shipping-bar-fill");
  if (text) text.textContent = view.text;
  if (meter) meter.style.width = Math.round(view.progress * 100) + "%";
}

function mount(config) {
  const link = typeof config.link === "string" ? config.link : "";
  const bar = document.createElement(link ? "a" : "div");
  if (link) bar.href = link;
  bar.className = "shipping-bar";
  bar.dataset.shippingBar = "";
  bar.setAttribute("role", "status");
  bar.setAttribute("aria-live", "polite");
  const background = hex(config.background);
  const color = hex(config.color);
  if (background) bar.style.background = background;
  if (color) bar.style.color = color;
  const text = document.createElement("span");
  text.className = "shipping-bar-text";
  const track = document.createElement("span");
  track.className = "shipping-bar-track";
  track.setAttribute("aria-hidden", "true");
  const meter = document.createElement("span");
  meter.className = "shipping-bar-fill";
  track.append(meter);
  bar.append(text, track);
  document.body.prepend(bar);
  return bar;
}

async function refresh(bar, config) {
  try {
    const response = await fetch(cartUrl, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    paint(bar, config, payload.cart || { totals: [] });
  } catch {
    paint(bar, config, { totals: [] });
  }
}

async function start() {
  const path = location.pathname;
  if (!document.body || path === "/admin" || path.startsWith("/admin/")) return;
  let config;
  try {
    const response = await fetch(configUrl, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    config = payload.config;
  } catch {
    return;
  }
  if (!config?.enabled) return;
  const bar = mount(config);
  window.addEventListener("flex-commerce-cart", (event) => {
    if (event.detail && Array.isArray(event.detail.totals)) {
      paint(bar, config, event.detail);
    }
  });
  if (typeof BroadcastChannel === "function") {
    const channel = new BroadcastChannel("flex-commerce-cart");
    channel.addEventListener("message", () => {
      refresh(bar, config);
    });
  }
  window.addEventListener("focus", () => {
    refresh(bar, config);
  });
  refresh(bar, config);
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      start();
    });
  } else {
    start();
  }
}
