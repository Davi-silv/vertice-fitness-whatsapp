#!/usr/bin/env node
/**
 * Sobe o servidor liberando a porta 3100 se já estiver ocupada.
 */
const { execSync, spawn } = require("child_process");
const path = require("path");

const PORT = String(process.env.PORT || 3100);

function freePort(port) {
  try {
    execSync(`fuser -k ${port}/tcp`, { stdio: "ignore" });
  } catch {
    // nada escutando — ok
  }
  try {
    execSync(`lsof -ti :${port} | xargs -r kill -9`, { stdio: "ignore", shell: true });
  } catch {
    // ok
  }
}

freePort(PORT);
// Pequena pausa para o SO liberar o socket.
setTimeout(() => {
  const child = spawn(process.execPath, [path.join(__dirname, "..", "server", "index.js")], {
    stdio: "inherit",
    env: process.env,
  });
  child.on("exit", (code, signal) => {
    if (signal) process.kill(process.pid, signal);
    process.exit(code ?? 0);
  });
}, 400);
