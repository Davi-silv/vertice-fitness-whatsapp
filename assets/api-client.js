(() => {
  const DEFAULT_LOCAL = "http://127.0.0.1:3100";
  const STORAGE_KEY = "vertice_api_base";

  function normalizeBase(value) {
    if (!value) return "";
    return String(value).trim().replace(/\/+$/, "");
  }

  function isLocalHost(hostname) {
    return hostname === "127.0.0.1" || hostname === "localhost" || hostname === "[::1]";
  }

  function isStaticHost() {
    const host = window.location.hostname || "";
    return /vercel\.app$/i.test(host) || /github\.io$/i.test(host);
  }

  function candidateBases() {
    const params = new URLSearchParams(window.location.search);
    const list = [];
    const fromQuery = normalizeBase(params.get("api"));
    const fromWindow = normalizeBase(window.VERTICE_API_BASE || "");
    const fromStorage = normalizeBase(localStorage.getItem(STORAGE_KEY) || "");
    const pageIsHttps = window.location.protocol === "https:";

    if (fromQuery) list.push(fromQuery);
    if (fromWindow) list.push(fromWindow);
    if (fromStorage) list.push(fromStorage);

    // Mesma origem (npm run dev / servidor Node).
    list.push("");

    const host = window.location.hostname;
    const port = window.location.port;
    const local = isLocalHost(host);

    // Só tenta http://127.0.0.1:3100 se a página também for http local
    // (HTTPS na Vercel bloqueia mixed content).
    if (!pageIsHttps && local && port !== "3100") {
      list.push(DEFAULT_LOCAL);
    }
    if (!pageIsHttps && local) {
      list.push(DEFAULT_LOCAL);
    }

    // Filtra bases http quando a página é https.
    return [...new Set(list)].filter((base) => {
      if (!base) return true;
      if (pageIsHttps && base.startsWith("http://")) return false;
      return true;
    });
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
    isStaticHost,
    localUrl: DEFAULT_LOCAL,
  };
})();
