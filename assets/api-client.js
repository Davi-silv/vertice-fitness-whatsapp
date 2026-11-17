(() => {
  const DEFAULT_LOCAL = "http://127.0.0.1:3100";
  const STORAGE_KEY = "vertice_api_base";

  function normalizeBase(value) {
    if (!value) return "";
    return String(value).trim().replace(/\/+$/, "");
  }

  function candidateBases() {
    const params = new URLSearchParams(window.location.search);
    const list = [];
    const fromQuery = normalizeBase(params.get("api"));
    const fromWindow = normalizeBase(window.VERTICE_API_BASE || "");
    const fromStorage = normalizeBase(localStorage.getItem(STORAGE_KEY) || "");
    const sameOrigin = "";

    if (fromQuery) list.push(fromQuery);
    if (fromWindow) list.push(fromWindow);
    if (fromStorage) list.push(fromStorage);
    list.push(sameOrigin);

    const host = window.location.hostname;
    const port = window.location.port;
    const isLocal = host === "127.0.0.1" || host === "localhost";
    if (isLocal && port !== "3100") {
      list.push(DEFAULT_LOCAL);
    }
    // Último recurso em ambiente local.
    if (isLocal) list.push(DEFAULT_LOCAL);

    return [...new Set(list)];
  }

  async function probe(base) {
    const url = `${base}/api/health`;
    const res = await fetch(url, {
      method: "GET",
      credentials: base ? "include" : "same-origin",
      headers: { Accept: "application/json" },
    });
    const type = res.headers.get("content-type") || "";
    if (!res.ok || !type.includes("application/json")) {
      throw new Error("health inválido");
    }
    const data = await res.json();
    if (!data?.ok) throw new Error("health ok=false");
    return data;
  }

  let ready = null;

  async function ensureApi() {
    if (ready) return ready;
    ready = (async () => {
      let lastError = null;
      for (const base of candidateBases()) {
        try {
          const health = await probe(base);
          if (base) localStorage.setItem(STORAGE_KEY, base);
          window.VERTICE_API_BASE = base;
          window.dispatchEvent(
            new CustomEvent("vertice:api-ready", { detail: { base, health } })
          );
          return { base, health };
        } catch (err) {
          lastError = err;
        }
      }
      window.dispatchEvent(new CustomEvent("vertice:api-offline", { detail: { error: lastError } }));
      throw lastError || new Error("Backend Node.js offline");
    })();
    try {
      return await ready;
    } catch (err) {
      ready = null;
      throw err;
    }
  }

  async function apiFetch(path, options = {}) {
    const { base } = await ensureApi();
    const url = `${base}${path.startsWith("/") ? path : `/${path}`}`;
    const headers = {
      Accept: "application/json",
      ...(options.headers || {}),
    };
    const res = await fetch(url, {
      ...options,
      headers,
      credentials: base ? "include" : "same-origin",
    });
    return res;
  }

  window.VerticeAPI = {
    ensureApi,
    apiFetch,
    getBase: () => window.VERTICE_API_BASE || "",
  };
})();
