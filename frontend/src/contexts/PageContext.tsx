import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { useLocation } from 'react-router-dom';

export interface PageAction {
  label: string;
  icon?: ReactNode;
  onClick: () => void;
  variant?: 'primary' | 'outline' | 'danger';
}

export interface DropdownAction {
  label: string;
  icon?: ReactNode;
  onClick: () => void;
  danger?: boolean;
  separator?: boolean; // affiche un séparateur avant cet item
}

export interface Breadcrumb {
  label: string;
  path?: string;
}

interface PageContextType {
  title: string;
  actions: PageAction[];
  dropdownActions: DropdownAction[];
  breadcrumbs: Breadcrumb[];
  setPage: (
    title: string,
    actions?: PageAction[],
    breadcrumbs?: Breadcrumb[],
    dropdownActions?: DropdownAction[]
  ) => void;
}

const PageContext = createContext<PageContextType>({
  title: '',
  actions: [],
  dropdownActions: [],
  breadcrumbs: [],
  setPage: () => {},
});

export function PageProvider({ children }: { children: ReactNode }) {
  const location = useLocation();
  const [title, setTitle] = useState('');
  const [actions, setActions] = useState<PageAction[]>([]);
  const [dropdownActions, setDropdownActions] = useState<DropdownAction[]>([]);
  const [breadcrumbs, setBreadcrumbs] = useState<Breadcrumb[]>([]);

  // Reset breadcrumbs/title/actions on every route change
  useEffect(() => {
    setTitle('');
    setActions([]);
    setBreadcrumbs([]);
    setDropdownActions([]);
  }, [location.pathname]);

  const setPage = useCallback((
    newTitle: string,
    newActions: PageAction[] = [],
    newBreadcrumbs: Breadcrumb[] = [],
    newDropdownActions: DropdownAction[] = []
  ) => {
    setTitle(newTitle);
    setActions(newActions);
    setBreadcrumbs(newBreadcrumbs);
    setDropdownActions(newDropdownActions);
  }, []);

  return (
    <PageContext.Provider value={{ title, actions, dropdownActions, breadcrumbs, setPage }}>
      {children}
    </PageContext.Provider>
  );
}

export function usePage() {
  return useContext(PageContext);
}
