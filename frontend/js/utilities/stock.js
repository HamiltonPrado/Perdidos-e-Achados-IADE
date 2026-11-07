// Estante da receção: 5 filas (A–E) × 5 colunas (1–5) = 25 prateleiras.
// Para mudar o tamanho da estante, muda só FILAS e COLUNAS.
const FILAS = ['A', 'B', 'C', 'D', 'E'];
const COLUNAS = [1, 2, 3, 4, 5];

export const PRATELEIRAS = FILAS.flatMap((fila) => COLUNAS.map((coluna) => `Prateleira ${fila}${coluna}`));

// Estados em que o objeto está fisicamente guardado na receção
export const OCUPA_PRATELEIRA = ['por_reclamar', 'reclamado'];
