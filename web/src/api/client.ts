export class ApiError extends Error {
  status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

let token: string | null = null

export function setToken(newToken: string | null): void {
  token = newToken
}

let onUnauthorized: () => void = () => {}

export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE'

export async function request<T>(method: Method, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = {}
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (token !== null) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const response = await fetch(`/api${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && token !== null) {
      onUnauthorized()
    }
    const message =
      typeof data?.error === 'string' ? data.error : `request failed (${response.status})`
    throw new ApiError(response.status, message)
  }

  return data as T
}
