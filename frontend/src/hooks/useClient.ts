import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/apiClient';

const RELATIONS = 'conjoint,enfants,sante_souhait,bae_prevoyance,bae_retraite,bae_epargne,revenus,passifs,actifs_financiers,biens_immobiliers,autres_epargnes,contrats';

async function fetchClient(id: string) {
  const { data } = await api.get(`/clients/${id}`, { params: { with: RELATIONS } });
  return data.data ?? data;
}

export function useClient(id: string | undefined) {
  return useQuery({
    queryKey: ['client', id],
    queryFn: () => fetchClient(id!),
    enabled: !!id,
    staleTime: 30_000,
  });
}

export function useInvalidateClient() {
  const qc = useQueryClient();
  return (id: string) => qc.invalidateQueries({ queryKey: ['client', id] });
}
