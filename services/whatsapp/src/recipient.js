export async function resolveRecipient(socket, phone) {
  const matches = await socket.onWhatsApp(phone);
  const recipient = matches?.find(result => result.exists && result.jid);
  if (!recipient) throw new Error('Recipient is not registered on WhatsApp');
  return recipient.jid;
}

// libsignal emits session objects with private keys directly to console.
export function safeConsoleArgs(args) {
  return args.filter(value => typeof value === 'string' || typeof value === 'number');
}
