/**
 * Extrae el mensaje más útil de una respuesta de error de la API:
 * primero el error de validación, luego el mensaje del backend, luego el respaldo.
 */
export function errorMessage(error: unknown, fallback: string): string {
  const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
  const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

  return validationError ?? response?.message ?? fallback;
}
