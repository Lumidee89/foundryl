import React from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import "@fontsource-variable/rubik";
import "../css/app.css";
import "../css/design.css";
const pages = import.meta.glob("./pages/*.tsx");
createInertiaApp({
    title: (title) => `${title} · Foundryl Build`,
    resolve: (name) => pages[`./pages/${name}.tsx`]() as any,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: "#f58220" },
});
