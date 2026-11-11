import FormularioSobreNovedad, { type PropsSobreNovedad } from '@/Components/FormularioSobreNovedad';

/**
 * Fe de errata (regla de negocio 1): los datos del evento se muestran
 * bloqueados y solo se registra el motivo de la corrección, con el autor. El
 * evento original no se toca y la errata deja constancia en el libro.
 */
export default function Create(props: PropsSobreNovedad) {
    return (
        <FormularioSobreNovedad
            {...props}
            titulo="Fe de errata"
            aviso={
                <>
                    Los datos de la novedad no se modifican: la fe de errata
                    registra el motivo de la corrección, con tu usuario y la
                    hora, y deja constancia en el libro de guardia.
                </>
            }
            campo="motivo"
            etiqueta="Motivo de la corrección"
            placeholder="Ej.: se cargó el PM equivocado; la novedad fue en la esquina de enfrente (PM-M-045)."
            boton="Registrar fe de errata"
            ruta="erratas.store"
        />
    );
}
