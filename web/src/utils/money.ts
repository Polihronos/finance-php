export function formatEur(amount: string): string {
  return `${amount.replace(/\.00$/, '').replace('.', ',')} EUR`
}
