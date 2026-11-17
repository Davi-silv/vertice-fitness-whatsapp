const app = require("./app");

const PORT = Number(process.env.PORT || 3100);

const server = app.listen(PORT, "0.0.0.0", () => {
  console.log(`Vértice Node.js em http://127.0.0.1:${PORT}`);
  console.log(`Login: teste@vertice.local / Teste@2026`);
});

server.on("error", (err) => {
  if (err && err.code === "EADDRINUSE") {
    console.error(`\nPorta ${PORT} já está em uso.`);
    console.error(`Rode: npm run dev  (libera a porta automaticamente)\n`);
    process.exit(1);
  }
  throw err;
});
