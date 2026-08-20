/**
 * Teclado en pantalla para el folio de Strumis.
 *
 * El campo lleva `inputMode="none"` a propósito: en la tablet el teclado del
 * sistema tapa medio formulario y ofrece autocorrector, que en un folio sólo
 * estorba. Éste tiene sólo mayúsculas, dígitos y guion, que es de lo que se
 * componen los folios.
 */

const LETRAS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
const NUMEROS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '-', '0'];

export function TecladoFolio({
    onTecla,
    onBorrar,
    onListo,
}: {
    onTecla: (caracter: string) => void;
    onBorrar: () => void;
    onListo: () => void;
}) {
    const tecla = 'rounded-lg border border-base-300 bg-base-100 py-3 text-base font-bold text-primary active:bg-primary active:text-primary-content';

    return (
        <div className="mt-[10px] flex flex-wrap items-start gap-3 rounded-xl border-2 border-base-300 bg-base-200 p-[10px]">
            <div className="grid flex-[1_1_300px] grid-cols-7 gap-[6px]">
                {LETRAS.map((letra) => (
                    <button key={letra} type="button" onClick={() => onTecla(letra)} className={tecla}>
                        {letra}
                    </button>
                ))}
            </div>
            <div className="grid flex-[0_0_168px] grid-cols-3 gap-[6px]">
                {NUMEROS.map((numero) => (
                    <button key={numero} type="button" onClick={() => onTecla(numero)} className={tecla}>
                        {numero}
                    </button>
                ))}
                <button type="button" onClick={onBorrar} className={`${tecla} border-error/40 bg-error/10 text-error`}>
                    ⌫
                </button>
                <button type="button" onClick={onListo} className={`${tecla} col-span-3 bg-primary text-primary-content`}>
                    Listo
                </button>
            </div>
        </div>
    );
}
