## PINTECH COLOMBIA S.A.S
1.0

**HISTORIAL DE REVISIÓN**

| Versión | Fecha Elaboración | Responsable Elaboración | Fecha Aprobación | Responsable Aprobación |
| --- | --- | --- | --- | --- |
| 1.0 | 26/03/2026 | Andrés Stiven Cebay Ceballos | 31/03/2026 |  |

**CAMBIOS RESPECTO A LA VERSIÓN ## PINTECH COLOMBIA S.A.S 
1.0

**HISTORIAL DE REVISIÓN**

| Versión | Fecha Elaboración | Responsable Elaboración | Fecha Aprobación | Responsable Aprobación |
| --- | --- | --- | --- | --- |
| 1.0 | 26/03/2026 | Andrés Stiven Cebay Ceballos | 1/04/2026 |  |

**CAMBIOS RESPECTO A LA VERSIÓN ANTERIOR**

| **VERSIÓN** | **MODIFICACIÓN RESPECTO VERSIÓN ANTERIOR** |
| --- | --- |
| 1.0 | Creación del Documento. |

## Tabla de Contenido.

## 1. Introducción

El presente documento tiene como finalidad especificar los requerimientos funcionales y no funcionales del sistema de gestión de planta para la empresa PINTECH COLOMBIA S.A.S. En él se describen las características del producto, los módulos del sistema, los perfiles de usuario y las restricciones técnicas que enmarcan el desarrollo de la solución.
La información aquí consignada fue obtenida a través del proceso de levantamiento de requerimientos realizado con Pintech Colombia S.A.S mediante entrevistas directas con el equipo de la organización.

### 1.1 Responsables e Involucrados

| **Nombre** | **Tipo (Responsable/ Involucrado)** | **Rol** | Cargo |
| --- | --- | --- | --- |
| Andrés Stiven Cebay Ceballos | Responsable | Analista/Desarrollador | Practicante ADSO |
| PINTECH COLOMBIA S.A.S | Involucrado | Cliente/Usuario | Administrador |

### 1.2 Referencias (Bibliografía o web Grafía)

| Nombre | Descripción | Link Referencia |
| --- | --- | --- |
| Entrevista — Pintech Colombia S.A.S | Levantamiento de requerimientos realizado el 27/03/2026 con el equipo de Pintech | N/A |

## 2. Características del producto

El sistema de gestión de planta para Pintech Colombia S.A.S tendrá las siguientes características generales:

- Será desarrollado como una **aplicación web**, accesible desde cualquier dispositivo con conexión a internet.
- Contará con un **sistema de autenticación** y control de acceso por roles, garantizando que cada usuario visualice únicamente la información correspondiente a su perfil.
- Permitirá la **gestión de inventarios** de materias primas y producto terminado en tiempo real.
- Incluirá un módulo de **cálculo de costos** basado en las formulaciones activas de cada producto.
- Contará con un sistema de **alertas automáticas** por niveles de stock, vencimientos y variaciones de costo.
- Soportará la **actualización automática de precios** ante variaciones porcentuales en los costos de materia prima.
- Permitirá la **generación y gestión de códigos QR** por envase, enlazando documentación técnica del producto.
- Estará diseñado para ser utilizado de forma **simultánea por hasta 6 usuarios** entre producción y área comercial.

## 3. Funciones del producto

Con base en la entrevista, se identificaron los siguientes módulos:

### 3.1 Gestión de Inventario de Materia Prima

Este módulo permitirá registrar, consultar y controlar el inventario de materias primas, aplicando metodología PEPS. Incluirá el control de fechas de vencimiento, generación de alertas de stock bajo y reposición, así como el análisis de curvas de consumo histórico que permitan proyectar re-compras más eficientes y en mejores condiciones comerciales.

### 3.2 Gestión de Producto Terminado

Este módulo permitirá consultar la disponibilidad de producto terminado por bodega en tiempo real, siendo accesible para el área comercial al momento de gestionar una venta. Incluye la funcionalidad de **Traslados**, permitiendo mover stock de la fábrica a los puntos de venta de forma trazable.

### 3.3 Gestión de Costos y Precios

Este módulo  permitirá calcular el costo de producción de cada producto según su formulación activa, y actualizar automáticamente los precios de venta cuando las variaciones de costo superen un umbral porcentual definido.

### 3.4 Gestión de Formulaciones

Este módulo permitirá registrar y administrar las fórmulas de producción asociadas a cada producto, sirviendo como base para el cálculo de costos y el control de consumo de materia prima.

### 3.5 Gestión de Alertas

Este módulo centralizará las notificaciones del sistema incluyendo alertas de stock bajo , vencimientos de materia prima y variaciones significativas en costos.

### 3.6 Gestión de Códigos QR

Este módulo permitirá generar y administrar códigos QR por envase de producto, enlazando la ficha técnica, ficha de seguridad y certificado de calidad correspondiente.

### 3.7 Gestión de Usuarios y Permisos

Este módulo permitirá administrar los usuarios del sistema, asignando roles y permisos según el perfil de cada persona, controlando el acceso a información como la estructura de costos.

### 3.8 Gestión de Órdenes de Producción

Este módulo permitirá registrar y gestionar las órdenes de producción de la planta, facilitando el seguimiento del proceso productivo y el control del consumo de materia prima asociado a cada orden. **Nota**: El sistema restringe el nacimiento de producto exclusivamente a bodegas marcadas como tipo "Fábrica". Este módulo será definido en detalle en una etapa posterior del levantamiento de requerimientos junto con el equipo de producción de Pintech Colombia S.A.S.

### 3.9 Reportes y Analíticas, Power BI

Este módulo permitirá visualizar métricas e indicadores clave del proceso productivo, incluyendo curvas de consumo histórico de materia prima, volúmenes de fabricación por periodo y análisis de costos Adicionalmente, buscará automatizar el proceso actual de generación de datos para los reportes de ventas y cartera que se alimentan en Power BI, reduciendo la intervención manual en la carga y transformación de información.

## 4 Características del usuario

### 4.1 Administrador (Gerente de Planta)

Este usuario tendrá acceso total al sistema. Será el encargado de gestionar las formulaciones, controlar el inventario de materia prima y producto terminado, administrar costos y precios, gestionar órdenes de producción y visualizar reportes y analíticas. Corresponde al perfil de Gerente de Planta de Pintech Colombia S.A.S. Cuenta con conocimientos intermedios en informática y manejo de herramientas ofimáticas.

### 4.2 Asistente de Producción

Este usuario tendrá acceso a los módulos operativos de la planta, incluyendo gestión de inventarios, órdenes de producción y alertas. No tendrá acceso a la estructura de costos ni a la configuración de precios. Corresponde al segundo al mando dentro del área de producción.

### 4.3 Comercial

Este usuario tendrá acceso limitado al sistema, enfocado exclusivamente en la consulta de disponibilidad de producto terminado por bodega. No tendrá acceso a costos, precios, formulaciones ni información productiva. Corresponde a los vendedores de Pintech Colombia S.A.S, con un máximo de cuatro usuarios con este perfil.

## **5. Especificación de requisitos**

### 5.1 Requisitos funcionales

| **FUNCIONALIDAD** | **TIPO (Esencial, Ideal, Opcional)** |
| --- | --- |
| RF01 - Iniciar sesión | Esencial |
| RF02 - Gestionar usuarios y permisos | Esencial |
| RF03 - Gestionar materias primas | Esencial |
| RF04 - Controlar inventario de materia prima | Esencial |
| RF05 - Gestionar alertas de inventario y vencimientos  | Esencial |
| RF06 - Gestionar curvas de consumo y proyección de re-compras | Esencial |
| RF07 - Gestionar formulaciones de productos | Esencial |
| RF08 - Calcular costos de producción | Esencial |
| RF09 - Gestionar precios de venta | Esencial |
| RF10 - Actualizar precios automáticamente por variación de costo | Esencial |
| RF11 - Gestionar inventario de producto terminado por bodega | Esencial |
| RF12 - Consultar disponibilidad de producto terminado | Esencial |
| RF13 - Gestionar órdenes de producción | Ideal |
| RF14 - Generar y gestionar códigos QR por envase | Ideal  |
| RF15 - Generar reportes y analíticas | Ideal |
| RF16 - Exportar datos para Power BI | Ideal |
| RF17 - Gestionar tipos de bodega | Esencial |
| RF18 - Gestionar traslados entre bodegas | Esencial |

**5.1.1 Clasificación de requisitos funcionales**

| **ID  del requerimiento** | RF01 |
| --- | --- |
| **Nombre del requerimiento** | Iniciar Sesión |
| **Descripción** | El sistema deberá permitir a los usuarios autenticarse mediante credenciales (usuario y contraseña). El acceso otorgado dependerá del rol asignado a cada usuario, restringiendo la visualización y operación de módulos según el perfil. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF02 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar usuarios y permisos |
| **Descripción** | El sistema deberá permitir al administrador crear, consultar, actualizar y eliminar usuarios. A cada usuario se le asignará un rol (Administrador, Asistente de Producción o Comercial) que determinará su nivel de acceso. RF02.1 - Registrar usuario. RF02.2 - Consultar usuario. RF02.3 - Actualizar usuario. RF02.4 - Eliminar usuario. RF02.5 - Asignar rol y permisos. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF03 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar materias primas |
| **Descripción** | El sistema deberá permitir registrar, consultar, actualizar y eliminar materias primas. Cada materia prima tendrá asociada información como nombre, unidad de medida, precio actual, fecha de vencimiento y stock disponible. RF03.1 - Registrar materia prima. RF03.2 - Consultar materia prima. RF03.3 - Actualizar materia prima. RF03.4 - Eliminar materia prima. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF04 |
| --- | --- |
| **Nombre del requerimiento** | Controlar inventario de materia prima |
| **Descripción** | El sistema deberá controlar las entradas y salidas de materia prima aplicando metodología PEPS (Primero en Entrar, Primero en Salir), garantizando que los lotes más antiguos sean consumidos primero. RF04.1 - Registrar entrada de materia prima. RF04.2 - Registrar salida de materia prima. RF04.3 - Consultar stock actual por materia prima. RF04.4 - Aplicar metodología PEPS. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF05 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar alertas de inventario y vencimientos  |
| **Descripción** | El sistema deberá generar alertas automáticas cuando el stock de una materia prima esté por debajo del nivel mínimo definido, cuando una materia prima esté próxima a vencer, y cuando se detecte una variación significativa en el precio de una materia prima. RF05.1 - Generar alerta de stock bajo. RF05.2 - Generar alerta de vencimiento próximo. RF05.3 - Generar alerta de variación de precio de materia prima. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF06 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar curvas de consumo y proyección de re-compras |
| **Descripción** | El sistema deberá analizar el histórico de consumo de cada materia prima y generar proyecciones que permitan identificar la frecuencia óptima de re-compra, ayudando a la organización a planificar adquisiciones con mayor anticipación y en mejores condiciones comerciales. RF06.1 - Registrar histórico de consumo por materia prima. RF06.2 - Generar curva de consumo. RF06.3 - Proyectar fecha y cantidad de re-compra. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF07 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar formulaciones de productos |
| **Descripción** | El sistema deberá permitir registrar, consultar, actualizar y eliminar las formulaciones asociadas a cada producto. Cada formulación indicará las materias primas requeridas y sus cantidades, sirviendo como base para el cálculo de costos y el control de consumo. RF07.1 - Registrar formulación. RF07.2 - Consultar formulación. RF07.3 - Actualizar formulación. RF07.4 - Eliminar formulación. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF08 |
| --- | --- |
| **Nombre del requerimiento** | Calcular costos de producción |
| **Descripción** | El sistema deberá calcular automáticamente el costo de producción de cada producto con base en su formulación activa y los precios actuales de las materias primas que la componen, permitiendo conocer en tiempo real el costo asociado a cada referencia fabricada. RF08.1 - Calcular costo por formulación activa. RF08.2 - Actualizar costo ante cambio de precio de materia prima. RF08.3 - Consultar histórico de costos por producto. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF09 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar precios de venta |
| **Descripción** | El sistema deberá permitir al administrador registrar y actualizar los precios de venta de cada producto, aplicando un margen de ganancia sobre el costo calculado. Los precios estarán disponibles en una lista actualizada accesible para el área de producción. RF09.1 - Registrar precio de venta por producto. RF09.2 - Consultar lista de precios. RF09.3 - Actualizar precio de venta manualmente. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF10 |
| --- | --- |
| **Nombre del requerimiento** | Actualizar precios automáticamente por variación de costo |
| **Descripción** | El sistema deberá actualizar automáticamente el precio de venta de un producto cuando la variación en su costo de producción supere un umbral porcentual definido por el administrador. Este umbral será configurable y por defecto se establece en un 3%. RF10.1 - Configurar umbral de variación porcentual. RF10.2 - Detectar variación de costo superior al umbral. RF10.3 - Actualizar precio de venta automáticamente. RF10.4 - Notificar al administrador sobre el cambio de precio. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF11 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar inventario de producto terminado por bodega |
| **Descripción** | El sistema deberá permitir registrar y controlar el inventario de producto terminado discriminado por bodega, manteniendo actualizado el stock disponible tras cada proceso de producción o despacho. RF11.1 - Registrar entrada de producto terminado. RF11.2 - Registrar salida de producto terminado. RF11.3 - Consultar stock por producto y bodega. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF12 |
| --- | --- |
| **Nombre del requerimiento** | Consultar disponibilidad de producto terminado |
| **Descripción** | El sistema deberá permitir al perfil comercial consultar en tiempo real la disponibilidad de producto terminado por bodega, sin necesidad de intermediarios, facilitando el cierre ágil de oportunidades de negocio. RF12.1 - Consultar disponibilidad por producto. RF12.2 - Consultar disponibilidad por bodega. RF12.3 - Filtrar por referencia o categoría de producto. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF13 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar órdenes de producción |
| **Descripción** | El sistema deberá permitir registrar y gestionar las órdenes de producción de la planta, asociando cada orden a una formulación y controlando el consumo de materia prima derivado de su ejecución. Los detalles específicos de este módulo serán definidos en una etapa posterior del levantamiento de requerimientos junto con el equipo de producción de Pintech Colombia S.A.S. RF13.1 - Registrar orden de producción. RF13.2 - Consultar orden de producción. RF13.3 - Actualizar estado de orden de producción. RF13.4 - Asociar consumo de materia prima a la orden. |
| **Prioridad** | Media |

| **ID  del requerimiento** | RF14 |
| --- | --- |
| **Nombre del requerimiento** | Generar y gestionar códigos QR por envase |
| **Descripción** | El sistema deberá permitir generar un código QR único por referencia de producto, el cual al ser escaneado redirigirá al usuario a la documentación técnica del producto, incluyendo ficha técnica, ficha de seguridad y certificado de calidad. El administrador podrá gestionar y actualizar los documentos asociados a cada código QR. RF14.1 - Generar código QR por referencia de producto. RF14.2 - Asociar documentación técnica al código QR. RF14.3 - Actualizar documentos asociados. RF14.4 - Visualizar documentación desde el código QR. |
| **Prioridad** | Media |

| **ID  del requerimiento** | RF15 |
| --- | --- |
| **Nombre del requerimiento** | Generar reportes y analíticas  |
| **Descripción** | El sistema deberá permitir visualizar métricas e indicadores clave del proceso productivo, incluyendo volúmenes de fabricación por periodo, histórico de costos y análisis de consumo de materia prima. Estos reportes servirán como base para la toma de decisiones estratégicas dentro de la organización. RF15.1 - Generar reporte de consumo de materia prima. RF15.2 - Generar reporte de volumen de producción por periodo. RF15.3 - Generar reporte de variación de costos. RF15.4 - Visualizar dashboard de indicadores. |
| **Prioridad** | Media |

| **ID  del requerimiento** | RF17 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar tipos de bodega |
| **Descripción** | El sistema deberá permitir distinguir entre bodegas de tipo "Fábrica" (donde ocurre la producción) y "Bodega/POS" (donde solo se almacena stock). Esta configuración es fija del sistema: Cali se define como Fábrica y Neiva como Bodega. El usuario no podrá modificar el tipo una vez configurado. |
| **Prioridad** | Alta |

| **ID  del requerimiento** | RF18 |
| --- | --- |
| **Nombre del requerimiento** | Gestionar traslados entre bodegas |
| **Descripción** | El sistema deberá permitir el traslado de productos terminados desde la fábrica hacia las bodegas de distribución. El proceso constará de dos pasos: envío desde el origen y confirmación de recepción en el destino para garantizar la trazabilidad del stock en tránsito. |
| **Prioridad** | Alta |

### 5.2 Requisitos no funcionales

| Categoría | **Usabilidad** |
| --- | --- |
| Requerimientos | RNF01 – Tiempo de aprendizaje: ≤4 horas para usuarios con conocimientos ofimáticas intermedios.
RNF02 – Interfaz responsive: Adaptable a desktop, tablet y móvil. |

| Categoría | **Confiabilidad** |
| --- | --- |
| Requerimientos | RNF03 – Disponibilidad: 99% uptime mensual.
RNF04 – Backup automático: Datos respaldados diariamente con retención de 30 días. |

| Categoría | **Seguridad** |
| --- | --- |
| Requerimientos | RNF05 – Autenticación: Hash bcrypt para contraseñas y gestión de sesión segura.
RNF06 – Control de acceso: RBAC estricto por roles (Administrador/Asistente/Comercial). |

| Categoría | **Eficiencia y Rendimiento** |
| --- | --- |
| Requerimientos | RNF07 – Tiempo respuesta: ≤2 segundos para consultas de inventario (95% percentil).
RNF08  Concurrencia: Soporte simultáneo para 6 usuarios sin degradación >10%. |

| Categoría | **Portabilidad** |
| --- | --- |
| Requerimientos | RNF09 – Navegadores: Chrome 90+, Firefox 88+, Edge 90+, Safari 14+.
RNF10 – Resolución: Compatible desde 1366x768 hasta 4K. |

| Categoría | **Mantenibilidad** |
| --- | --- |
| Requerimientos | RNF11 – Código modular: Arquitectura MVC con separación clara de responsabilidades.
RNF12 – Documentación técnica: Documentación de endpoints en caso de exposición de API. |

| Categoría | **Soportabilidad y operatividad** |
| --- | --- |
| Requerimientos | RNF13 – Exportación: CSV/Excel compatible con Power BI (UTF-8 encoding).
RNF14 – Logs: Auditoría completa de acciones críticas (CRUD usuarios, órdenes producción). |

## 6**. Restricciones del software**

- El sistema será utilizado inicialmente por un máximo de 6 usuarios simultáneos.
- No se contempla desarrollo de aplicación móvil en esta fase.
- La infraestructura dependerá de conexión a internet estable.
- La integración con Power BI se realizará mediante exportación de datos, no conexión directa en tiempo real.

## 7**. Anexos**ANTERIOR**

| **VERSIÓN** | **MODIFICACIÓN RESPECTO VERSIÓN ANTERIOR** |
| --- | --- |
| 1.0 | Creación de documento |

## Tabla de Contenido.

## 1. Introducción

El presente documento tiene como finalidad describir el problema identificado en la empresa PINTECH COLOMBIA S.A.S y contextualizar el desarrollo de una solución tecnológica orientada a mejorar sus procesos internos. En él se recoge el análisis de la situación actual, la situación esperada, la justificación de la solución propuesta y los aspectos legales aplicables.
Pintech Colombia S.A.S es una empresa dedicada a la fabricación y comercialización de pinturas industriales, automotrices y arquitectónicas, con sedes en Neiva (Huila) y Cali (Valle del Cauca), que se encuentra en una etapa de crecimiento que exige fortalecer sus procesos operativos mediante el uso de tecnología.

Este documento servirá como base para la definición de requisitos del sistema y su posterior desarrollo.

### 1.1 Responsables e Involucrados

| **Nombre** | **Tipo (Responsable/ Involucrado)** | **Rol** | Cargo |
| --- | --- | --- | --- |
| Andrés Stiven Cebay Ceballos | Responsable | Analista/Desarrollador | Practicante ADSO |
| PINTECH COLOMBIA S.A.S | Involucrado | Cliente/Usuario | Administrador |

### 1.2 Referencias (Bibliografía o Webgrafía)

| Nombre | Descripción | Link Referencia |
| --- | --- | --- |
| Entrevista — Pintech Colombia S.A.S | Levantamiento de requerimientos realizado el 27/03/2026 con el equipo de Pintech | N/A |
| DIAN | Facturación electrónica obligatoria | https://www.dian.gov.co/facturaelectronica |
| MinTIC | Lineamientos  desarrollo software | [https://www.mintic.gov.co/portal/inicio](https://www.mintic.gov.co/portal/inicio/Lineamientos) |
| MinCIT | Reglamentos técnicos pinturas | https://www.mincit.gov.co/temas-interes/reglamentos-tecnicos |

## 2**. Descripción General**

La empresa Pintech Colombia S.A.S, dedicada a la fabricación y comercialización de pinturas industriales, automotrices y arquitectónicas, se encuentra en un proceso de crecimiento y consolidación de su marca propia, pasando de un enfoque principalmente comercial a uno productivo.

En este contexto, se ha identificado la ausencia de un sistema de información que integre los procesos de su planta de producción con el área comercial. Actualmente la empresa gestiona sus inventarios de materia prima, fórmulas de producción, costos y disponibilidad de producto terminado de forma manual a través de hojas de cálculo en Excel y almacenamiento en Dropbox. Esta situación genera demoras en la consulta de disponibilidad de inventario, riesgo de desabastecimiento, y un proceso lento y propenso a errores para el cálculo de costos y actualización de precios. Se propone desarrollar una aplicación web de gestión de planta que centralice y automatice estos procesos, incluyendo la generación de códigos QR por envase que enlacen documentación técnica del producto.

## 3**. Situación Actual**

Actualmente, la empresa no cuenta con un sistema de gestión de planta. La información relacionada con materias primas, formulaciones, costos y productos terminados se encuentra distribuida en múltiples archivos de Excel, compartidos a través de Dropbox.

Adicionalmente, la empresa utiliza el software contable World Office para la gestión financiera, y herramientas como Power BI para el análisis de información, las cuales requieren procesos manuales de carga y transformación de datos.

Las principales limitaciones del estado actual son:

- Falta de integración entre áreas (producción, inventario y comercial)
- Procesos manuales que incrementan el riesgo de error
- Ausencia de información en tiempo real
- Dependencia de archivos Excel para procesos críticos
- Falta de automatización en el cálculo de costos

En particular, se presentan las siguientes problemáticas:

🔻 **Inventarios**

- No existe control automatizado de niveles de stock
- No se generan alertas de vencimiento o reposición
- No se aplica de forma sistemática la metodología PEPS

🔻 **Producción**

- No hay gestión estructurada de órdenes de producción
- Falta trazabilidad del consumo de materia prima

🔻 **Comercial**

- Dependencia de consultas manuales para validar disponibilidad
- Retrasos en la atención de clientes
- Pérdida ocasional de oportunidades de negocio

🔻 **Costos**

- Cálculo manual basado en hojas de cálculo
- Falta de actualización oportuna ante cambios en materia prima

🔻 **Datos**

- Procesos manuales para alimentar reportes
- Baja eficiencia en integración con herramientas analíticas

Esta situación obliga a la empresa a operar de forma reactiva, sin capacidad de anticiparse a necesidades de producción o abastecimiento, afectando su eficiencia operativa y capacidad de crecimiento.

## 4**. Situación Esperada**

Se espera implementar una aplicación web que permita centralizar y optimizar la gestión de la planta, brindando visibilidad en tiempo real sobre el estado de los inventarios y procesos productivos.

El sistema deberá permitir:

- Control del inventario de materias primas y producto terminado
- Aplicación de metodologías de rotación de inventario (PEPS)
- Generación de alertas relacionadas con niveles de stock y vencimientos
- Cálculo de costos de producción basado en formulaciones
- Soporte para la gestión y actualización de precios
- Consulta de disponibilidad de productos por parte del área comercial
- Reducción de procesos manuales
- El sistema deberá facilitar la exportación o integración de datos con herramientas de análisis como Power BI, reduciendo los procesos manuales actuales de carga de información.

Adicionalmente, se espera incorporar un mecanismo mediante códigos QR que permita a los clientes acceder a información técnica de los productos, como fichas técnicas, fichas de seguridad y certificados de calidad.

Con esta solución, la empresa busca mejorar la eficiencia operativa, fortalecer la toma de decisiones basada en datos y soportar su crecimiento de manera organizada.

## 5**. Justificación**

La decisión de desarrollar un sistema a medida se fundamenta en que las soluciones ERP disponibles en el mercado, implican altos costos de implementación y adaptación, los cuales superan la capacidad de inversión actual de la empresa.

Adicionalmente, dichas soluciones no se ajustan completamente a las necesidades específicas del proceso productivo de la organización.

Un desarrollo propio permitirá:

- Adaptar el sistema a los procesos reales de la empresa
- Implementar funcionalidades según prioridades del negocio
- Tener control total sobre la información estratégica
- Escalar y evolucionar el sistema conforme al crecimiento de la organización

Este proyecto tiene un carácter prioritario, dado que la empresa se encuentra en una etapa de expansión y requiere fortalecer sus procesos internos para garantizar eficiencia, competitividad y sostenibilidad.

## 6**. Aspectos Legales aplicables**

| Norma o Ley | Descripción | Enlace |
| --- | --- | --- |
| **Ley de Protección de Datos Personales o Ley 1581 de 2012** | Reconoce y protege el derecho que tienen todas las personas a conocer, actualizar y rectificar las informaciones que se hayan recogido sobre ellas en bases de datos o archivos que sean susceptibles de tratamiento por entidades de naturaleza pública o privada. | https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981 |
| Decreto 1377 de 2013 | Reglamenta la Ley 1581 de 2012 | https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=53646 |
| Ley 527 de 1999 (Comercio Electrónico) | Regula el comercio electrónico, mensajes de datos y firma electrónica | https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=50583 |
| Decreto 1074 de 2015 (Código de Comercio Electrónico) | Decreto único reglamentario del sector comercio (incluye comercio electrónico) | https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=76608 |
| Ley 1731 de 2012 | Reglamenta parcialmente la Ley 527 de 1999 sobre comercio electrónico | https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=58834 |
| **Resolución 1154/2016** | Etiquetado pinturas (MinCIT) | https://www.mincit.gov.co/mincomercioexterior/g/tbt/n/col/216/add.1  |