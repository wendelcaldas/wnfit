// One controlled round. Keep this run ID when retrying; the bridge deduplicates it.
// No HTTP endpoint, scheduler, real student or charge is created.
const samples = {
  "boas_vindas": "Ola, Wendel! Seja bem-vindo(a) ao WNFit. Seu plano Mensal ja esta ativo e o primeiro vencimento fica para 16/11/2026.",
  "treino_atualizado": "Olá, Wendel! Seu treino Força e mobilidade está disponível. Acesse a área do aluno para ver a ficha.",
  "vencimento_proximo": "Olá, Wendel! Sua mensalidade de R$ 150,00 vence em dois dias, em 16/11/2026.",
  "vencimento_hoje": "Olá, Wendel! Sua mensalidade de R$ 150,00 vence hoje.",
  "cobranca_atrasada": "Olá, Wendel! Sua mensalidade de R$ 150,00 está em aberto. Se você já pagou, pode desconsiderar este aviso.",
  "agendamento_confirmado": "Olá, Wendel! Seu agendamento para Pilates está confirmado para 16/11/2026 às 08:00."
};
const recipient = "+5571996651964";
const run = "pilot_20261114_v1";
const base = (Bun.env.BRIDGE_URL || "").replace(/\/$/, "");
const token = Bun.env.BRIDGE_TOKEN;
if (!base || !token) throw new Error("Missing bridge configuration");
const headers = { Authorization: "Bearer " + token, "Content-Type": "application/json" };
async function request(path: string, init: RequestInit = {}) {
  return fetch(base + path, { ...init, headers, signal: AbortSignal.timeout(35000) });
}
const health = await request("/health");
if (!health.ok || !(await health.json()).connected) throw new Error("Bridge disconnected; no samples sent");
console.info("BRIDGE_CONNECTED");
const accepted: {type: string; id: string}[] = [];
for (const [type, body] of Object.entries(samples)) {
  const response = await request("/welcome", {
    method: "POST",
    body: JSON.stringify({ key: "welcome-test:0:" + run + "_" + type, to: recipient, body: "[TESTE WNFit · " + type + "]\n" + body }),
  });
  const result = await response.json();
  if (!response.ok || typeof result.id !== "string" || !/^[A-Za-z0-9_-]{1,128}$/.test(result.id)) {
    console.error(JSON.stringify({ type, http: response.status, status: "STOPPED_CHECK_BEFORE_RETRY" }));
    process.exit(1);
  }
  accepted.push({type, id: result.id});
  console.info(JSON.stringify({ type, id: result.id, status: "accepted" }));
}
for (let round = 0; round < 3; round++) {
  await Bun.sleep(5000);
  let delivered = 0;
  for (const item of accepted) {
    const response = await request("/messages/" + item.id);
    const receipt = await response.json();
    console.info(JSON.stringify({ ...item, http: response.status, status: receipt.status }));
    if (receipt.status === "delivered" || receipt.status === "read") delivered++;
  }
  if (delivered === accepted.length) break;
}
console.info("SMOKE_TEST_FINISHED");

