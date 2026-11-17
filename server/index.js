const path = require("path");
const express = require("express");
const session = require("express-session");
const cookieParser = require("cookie-parser");
const { ROOT } = require("./db");
const authRoutes = require("./routes/auth");
const adminRoutes = require("./routes/admin");
const { chatHandler } = require("./chat");
const { currentUser } = require("./middleware");

const app = express();
const PORT = Number(process.env.PORT || 3100);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(
  session({
    name: "vertice.sid",
    secret: process.env.SESSION_SECRET || "vertice-fitness-dev-secret",
    resave: false,
    saveUninitialized: true,
    cookie: {
      httpOnly: true,
      sameSite: "lax",
      maxAge: 1000 * 60 * 60 * 12,
    },
  })
);

app.use("/api/auth", authRoutes);
app.post("/api/chat", chatHandler);
app.use("/api/admin", adminRoutes);

app.get("/api/health", (_req, res) => {
  res.json({ ok: true, runtime: "node", port: PORT });
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
  (_req, res) => {
    res.redirect(302, "/");
  }
);

app.use(express.static(ROOT, { index: false, extensions: ["html"] }));

const server = app.listen(PORT, "0.0.0.0", () => {
  console.log(`Vértice Node.js em http://127.0.0.1:${PORT}`);
  console.log(`Login: teste@vertice.local / Teste@2026`);
});

server.on("error", (err) => {
  if (err && err.code === "EADDRINUSE") {
    console.error(`\nPorta ${PORT} já está em uso.`);
    console.error(`Pare o processo anterior ou rode:`);
    console.error(`  fuser -k ${PORT}/tcp && npm run dev\n`);
    process.exit(1);
  }
  throw err;
});
