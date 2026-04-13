## PINTECH COLOMBIA S.A.S
1.0

**HISTORIAL DE REVISIÓN**

| Versión | Fecha Elaboración | Responsable Elaboración | Fecha Aprobación | Responsable Aprobación |
| --- | --- | --- | --- | --- |
| 1.0 | 26/03/2026 | Andrés Stiven Cebay Ceballos | 31/03/2026 |  |

**CAMBIOS RESPECTO A LA VERSIÓN ANTERIOR**

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
- **Ausencia de un mecanismo formal de traslados entre sedes** (Cali-Neiva) que garantice la trazabilidad de la mercancía en tránsito.

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
- **Gestión logística de traslados**: El sistema diferenciará entre la sede de Cali (Fábrica) y Neiva (Bodega/Venta), implementando un flujo de traslados con confirmación en destino para evitar pérdidas de stock.
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