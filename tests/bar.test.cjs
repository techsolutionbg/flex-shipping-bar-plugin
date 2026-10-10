const { test } = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

async function load() {
  const source = fs.readFileSync(path.join(__dirname, "../assets/bar.js"), "utf8");
  return import("data:text/javascript;base64," + Buffer.from(source).toString("base64"));
}

const config = {
  enabled: true,
  threshold_minor: 5000,
  currency: "EUR",
  message_empty: "над {threshold}",
  message_progress: "остават {remaining}",
  message_reached: "готово {subtotal}",
};

test("bar follows the cart subtotal in the store currency", async () => {
  const { barView, subtotalFor } = await load();
  const cart = { totals: [{ currency: "BGN", amount_minor: 9000 }, { currency: "EUR", amount_minor: 2000 }] };
  assert.equal(subtotalFor(cart, "EUR"), 2000);
  assert.equal(barView(config, { totals: [] }).state, "empty");
  assert.match(barView(config, { totals: [] }).text, /над /);
  const progress = barView(config, cart);
  assert.equal(progress.state, "progress");
  assert.match(progress.text, /остават /);
  assert.equal(progress.progress, 0.4);
  const reached = barView(config, { totals: [{ currency: "EUR", amount_minor: 5000 }] });
  assert.equal(reached.state, "reached");
  assert.equal(reached.progress, 1);
  assert.match(reached.text, /готово /);
});
