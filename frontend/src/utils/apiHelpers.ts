/**
 * API Helpers - Utilitaires pour extraire les données des API Resources Laravel
 *
 * Le backend utilise maintenant des API Resources qui wrappent les données dans { data: ... }
 */

/**
 * Extrait les données d'une réponse API Resource Laravel (élément unique)
 *
 * @example
 * const response = await api.get('/api/clients/1');
 * const client = extractData<Client>(response); // Extrait response.data.data
 */
export function extractData<T>(response: any): T {
  return response.data?.data || response.data;
}

/**
 * Extrait un tableau de données d'une collection API Resource
 *
 * @example
 * const response = await api.get('/api/clients');
 * const clients = extractCollection<Client>(response); // Extrait response.data.data
 */
export function extractCollection<T>(response: any): T[] {
  return response.data?.data || response.data || [];
}

/**
 * Extrait les données avec un message additionnel
 *
 * @example
 * const response = await api.post('/api/audio/upload', formData);
 * const { data, message } = extractWithMessage(response);
 */
export function extractWithMessage<T>(response: any): {
  data: T;
  message?: string;
} {
  return {
    data: response.data?.data || response.data,
    message: response.data?.message,
  };
}

/**
 * Vérifie si la réponse est une API Resource Laravel
 */
export function isApiResource(response: any): boolean {
  return response.data && typeof response.data === 'object' && 'data' in response.data;
}
