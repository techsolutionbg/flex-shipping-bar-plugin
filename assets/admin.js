export function createPage(host) {
  const {React, components: C, request, toast, icons} = host;
  const h = React.createElement;
  const base = "/admin/plugins/flex/shipping-bar";

  function money(minor, currency) {
    const value = Number(minor) || 0;
    try {
      return new Intl.NumberFormat("bg-BG", {style: "currency", currency: currency || "EUR"}).format(value / 100);
    } catch {
      return (value / 100).toFixed(2) + " " + (currency || "EUR");
    }
  }

  function fill(template, amounts) {
    return String(template || "")
      .replaceAll("{threshold}", amounts.threshold)
      .replaceAll("{remaining}", amounts.remaining)
      .replaceAll("{subtotal}", amounts.subtotal);
  }

  function SettingsPage({navigate}) {
    const [payload, setPayload] = React.useState(null);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState("");
    React.useEffect(() => {
      request(base + "/settings").then(setPayload).catch((reason) => setError(reason.message));
    }, []);
    const values = payload?.settings;
    const currency = payload?.currency || "EUR";
    function update(patch) {
      setPayload((current) => ({...current, settings: {...current.settings, ...patch}}));
    }
    async function save(event) {
      event.preventDefault();
      setBusy(true);
      setError("");
      try {
        const data = await request(base + "/settings", "PUT", {
          enabled: !!values.enabled,
          threshold_minor: Math.max(0, Math.round(Number(values.threshold_minor) || 0)),
          message_empty: values.message_empty,
          message_progress: values.message_progress,
          message_reached: values.message_reached,
          background: values.background,
          color: values.color,
          link: values.link || "",
        });
        setPayload(data);
        toast?.("Настройките за безплатна доставка са запазени.");
      } catch (reason) {
        setError(reason.message);
      } finally {
        setBusy(false);
      }
    }
    const threshold = Number(values?.threshold_minor) || 0;
    const amounts = {
      threshold: money(threshold, currency),
      remaining: money(Math.round(threshold * 0.6), currency),
      subtotal: money(Math.round(threshold * 0.4), currency),
    };
    const preview = !values ? "" : threshold > 0 ? fill(values.message_progress, amounts) : fill(values.message_empty, amounts);
    const previewWidth = threshold > 0 ? "40%" : "0%";
    return h(React.Fragment, null,
      h("div", {className: "react-page-heading"},
        h("div", null,
          h("h1", null, "Безплатна доставка"),
          h(C.Breadcrumbs, {
            items: [{label: "Безплатна доставка"}],
            onHomeClick: () => navigate?.("/"),
          }))),
      error && h("p", {role: "alert"}, error),
      !payload
        ? h("p", {role: "status"}, error ? "Настройките не могат да бъдат заредени." : "Зареждане…")
        : !values
          ? h("p", {role: "alert"}, "Първо инсталирайте и активирайте Flex Commerce.")
          : h("form", {onSubmit: save, className: "react-edit-form shipping-bar-edit-form"},
              h("fieldset", {disabled: busy || !payload.commerce_active},
                !payload.commerce_active && h("p", {role: "alert"}, "Flex Commerce трябва да е инсталиран и активиран."),
                h(C.CollapsibleSection, {title: "Лента", icon: icons.Settings, storageKey: "shipping-bar-general", defaultOpen: true},
                  h("div", {className: "react-form-grid"},
                    h("p", {className: "react-field-hint"}, "Прагът важи и в поръчката: фиксираната доставка и Еконт стават безплатни, когато междинната сума го достигне. Изключената лента само скрива съобщението. 0 спира безплатната доставка."),
                    h(C.CheckboxField, {
                      label: "Показвай лентата",
                      checked: !!values.enabled,
                      onChange: (event) => update({enabled: event.target.checked}),
                      description: "Лентата стои над хедъра и се обновява заедно с количката.",
                    }),
                    h("label", {className: "react-form-field"}, "Праг (" + currency + ")",
                      h(C.Input, {
                        type: "number",
                        min: 0,
                        step: "0.01",
                        required: true,
                        value: (Number(values.threshold_minor) || 0) / 100,
                        onChange: (event) => update({threshold_minor: Math.round(Number(event.target.value) * 100)}),
                      }),
                      h("small", {className: "react-field-hint"}, "Междинна сума на продуктите, без доставка.")),
                    h("label", {className: "react-form-field"}, "Празна количка",
                      h(C.Input, {value: values.message_empty || "", onChange: (event) => update({message_empty: event.target.value})}),
                      h("small", {className: "react-field-hint"}, "Места: {threshold}, {remaining}, {subtotal}.")),
                    h("label", {className: "react-form-field"}, "Под прага",
                      h(C.Input, {value: values.message_progress || "", onChange: (event) => update({message_progress: event.target.value})})),
                    h("label", {className: "react-form-field"}, "Достигнат праг",
                      h(C.Input, {value: values.message_reached || "", onChange: (event) => update({message_reached: event.target.value})})),
                    h("label", {className: "react-form-field"}, "Линк",
                      h(C.Input, {value: values.link || "", placeholder: "/catalog", onChange: (event) => update({link: event.target.value})}),
                      h("small", {className: "react-field-hint"}, "Незадължителен. Вътрешен път или http(s) адрес. Цялата лента става връзка.")),
                    h("label", {className: "react-form-field"}, "Фон",
                      h(C.Input, {type: "color", value: values.background || "#0f6b4c", onChange: (event) => update({background: event.target.value})})),
                    h("label", {className: "react-form-field"}, "Текст",
                      h(C.Input, {type: "color", value: values.color || "#ffffff", onChange: (event) => update({color: event.target.value})})))),
                h("div", {className: "shipping-bar-preview-wrap"},
                  h("p", {className: "react-field-hint"}, threshold > 0 ? "Преглед при около 40% от прага." : "Преглед при празна количка."),
                  h("div", {
                    className: "shipping-bar is-progress",
                    style: {background: values.background, color: values.color},
                  },
                    h("span", {className: "shipping-bar-text"}, preview),
                    h("span", {className: "shipping-bar-track", "aria-hidden": true},
                      h("span", {className: "shipping-bar-fill", style: {width: previewWidth}})))),
                h("div", {className: "react-form-actions"},
                  h(C.LoadingButton, {
                    type: "submit",
                    loading: busy,
                    disabled: busy || !payload.commerce_active,
                    icon: h(icons.Save),
                  }, "Запази настройките")))));
  }

  return function ShippingBarPage({navigate}) {
    return h(React.Fragment, null,
      h("link", {rel: "stylesheet", href: "/extensions/flex/shipping-bar/assets/assets/bar.css?v=1"}),
      h(SettingsPage, {navigate}));
  };
}
