const path = require("path");
const express = require("express");
const cookieSession = require("cookie-session");
const cookieParser = require("cookie-parser");
const { ROOT, ON_VERCEL, DB_PATH } = require("./db");
const authRoutes = require("./routes/auth");
const adminRoutes = require("./routes/admin");
const { chatHandler } = require("./chat");
const { currentUser } = require("./middleware");

const app = express();
const SESSION_SECRET = process.env.SESSION_SECRET || "vertice-fitness-dev-secret";

function isDevOrigin(origin = "") {
  return (
    /^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/i.test(origin) ||
    /^http:\/\/\[::1\](:\d+)?$/i.test(origin)
  );
}

app.set("trust proxy", 1);

app.use((req, res, next) => {
  const origin = req.headers.origin || "";
  if (origin && isDevOrigin(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin);
    res.setHeader("Access-Control-Allow-Credentials", "true");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type, Accept, X-CSRF-Token");
    res.setHeader("Access-Control-Allow-Methods", "GET,POST,PUT,PATCH,DELETE,OPTIONS");
  }
  if (req.method === "OPTIONS") return res.sendStatus(204);
  next();
});

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser(SESSION_SECRET));
app.use(
  cookieSession({
    name: "vertice_session",
    keys: [SESSION_SECRET],
    maxAge: 1000 * 60 * 60 * 12,
    httpOnly: true,
    sameSite: "lax",
    secure: ON_VERCEL,
  })
);

app.use("/api/auth", authRoutes);
app.post("/api/chat", chatHandler);
app.use("/api/admin", adminRoutes);

app.get("/api/health", (_req, res) => {
  res.json({
    ok: true,
    runtime: "node",
    vercel: ON_VERCEL,
    db: DB_PATH,
  });
});

app.use("/assets", express.static(path.join(ROOT, "assets")));
app.use(
  "/admin",
  (req, res, next) => {
    if (/\.(css|js|png|jpg|svg|ico|woff2?)$/i.test(req.path)) return next();
    if (!currentUser(req)) return res.redirect("/?login=1");
    next();
  },
  express.static(path.join(ROOT, "public", "admin"), { index: "index.html" })
);

app.get("/", (_req, res) => {
  res.sendFile(path.join(ROOT, "index.html"));
});

app.get(
  [
    "/demos/vertice-fitness-whatsapp",
    "/demos/vertice-fitness-whatsapp/",
    "/demos/vertice-fitness-whatsapp/index.html",
  ],
  (_req, res) => res.redirect(302, "/")
);

app.get("/gestao-local.html", (_req, res) => {
  res.redirect(302, "/?login=1");
});

app.use(express.static(ROOT, { index: false, extensions: ["html"] }));

module.exports = app;
