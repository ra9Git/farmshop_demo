import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import StoresApp from './StoresApp.jsx';

createRoot(document.getElementById('stores-root')).render(
  <StrictMode>
    <StoresApp />
  </StrictMode>,
);