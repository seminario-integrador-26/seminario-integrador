import FormularioSobreNovedad, { type PropsSobreNovedad } from '@/Components/FormularioSobreNovedad';

/**
 * Ampliación de una novedad: suma información posterior (qué pasó después,
 * datos que llegaron más tarde) sin tocar la novedad, que es inmutable. Deja
 * constancia en el libro de guardia, como la fe de errata.
 */
export default function Create(props: PropsSobreNovedad) {
    return (
        <FormularioSobreNovedad
            {...props}
            titulo="Ampliación"
            aviso={
                <>
                    Los datos de la novedad no se modifican: la ampliación
                    agrega información posterior, con tu usuario y la hora, y
                    deja constancia en el libro de guardia.
                </>
            }
            campo="detalle"
            etiqueta="Detalle de la ampliación"
            placeholder="Ej.: la persona fue aprehendida por el móvil 12 en Bv. España y Santa Fe."
            boton="Registrar ampliación"
            ruta="ampliaciones.store"
        />
    );
}
