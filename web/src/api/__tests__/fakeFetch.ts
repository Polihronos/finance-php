import { vi } from 'vitest'

export function fakeFetch(status: number, body: string) {
  const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(new Response(body, { status }))
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}
