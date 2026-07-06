# Diagramas de Casos de Uso — Sistema Gestión de Planta
## PINTECH COLOMBIA S.A.S

---

## 1. Diagrama General del Sistema

> Muestra los tres perfiles de usuario y los subsistemas a los que cada uno tiene acceso.

```plantuml
@startuml
title Sistema de Gestión de Planta — PINTECH COLOMBIA S.A.S

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12

skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam rectangle {
    BackgroundColor #FDFEFE
    BorderColor #5D6D7E
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador\n(Gerente de Planta)" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Comercial\n(Vendedor)" as Comercial
actor "Sistema\n(Automático)" as Sistema

rectangle "SISTEMA DE GESTIÓN DE PLANTA" {
    (Usuarios y Permisos) as U
    (Inventario de\nMateria Prima) as MP
    (Alertas) as AL
    (Producto Terminado) as PT
    (Formulaciones) as FO
    (Costos y Precios) as CP
    (Órdenes de\nProducción) as OP
    (Códigos QR) as QR
    (Reportes y\nAnalíticas) as RE
}

Admin --> U
Admin --> MP
Admin --> AL
Admin --> PT
Admin --> FO
Admin --> CP
Admin --> OP
Admin --> QR
Admin --> RE

Asistente --> MP
Asistente --> AL
Asistente --> PT
Asistente --> OP
Asistente --> RE

Comercial --> PT
Comercial --> QR

Sistema --> MP
Sistema --> AL
Sistema --> CP

note bottom of Admin
  Acceso total al sistema.
  Gestiona costos, precios
  y configuración general.
end note

note bottom of Asistente
  Acceso operativo.
  No ve costos ni precios.
end note

note bottom of Comercial
  Solo consulta disponibilidad
  de producto terminado y QR.
end note

note bottom of Sistema
  Ejecuta procesos automáticos:
  PEPS, alertas, recálculo
  de costos y precios.
end note

@enduml
```

---

## 2. Subsistema: Gestión de Usuarios y Permisos
**Casos de uso:** CU01 al CU06

| Código | Nombre | Actor |
|--------|--------|-------|
| CU01 | Iniciar sesión | Todos |
| CU02 | Registrar usuario | Administrador |
| CU03 | Consultar usuario | Administrador |
| CU04 | Actualizar usuario | Administrador |
| CU05 | Eliminar usuario | Administrador |
| CU06 | Asignar rol y permisos | Administrador |

```plantuml
@startuml
title Subsistema: Gestión de Usuarios y Permisos

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Comercial" as Comercial

rectangle "Gestión de Usuarios y Permisos" {
    (CU01: Iniciar sesión) as CU01
    (CU02: Registrar usuario) as CU02
    (CU03: Consultar usuario) as CU03
    (CU04: Actualizar usuario) as CU04
    (CU05: Eliminar usuario) as CU05
    (CU06: Asignar rol\ny permisos) as CU06
}

Admin --> CU01
Admin --> CU02
Admin --> CU03
Admin --> CU04
Admin --> CU05
Admin --> CU06

Asistente --> CU01
Comercial --> CU01

CU02 ..> CU01 : <<include>>
CU03 ..> CU01 : <<include>>
CU04 ..> CU01 : <<include>>
CU05 ..> CU01 : <<include>>
CU06 ..> CU01 : <<include>>

note right of CU06
  Define si el usuario es
  Administrador, Asistente
  o Comercial.
end note

@enduml
```

---

## 3. Subsistema: Gestión de Inventario de Materia Prima
**Casos de uso:** CU07 al CU17

| Código | Nombre | Actor |
|--------|--------|-------|
| CU07 | Registrar materia prima | Administrador |
| CU08 | Consultar materia prima | Admin, Asistente |
| CU09 | Actualizar materia prima | Administrador |
| CU10 | Eliminar materia prima | Administrador |
| CU11 | Registrar entrada de materia prima | Admin, Asistente |
| CU12 | Registrar salida de materia prima | Admin, Asistente |
| CU13 | Aplicar metodología PEPS | Sistema |
| CU14 | Consultar stock actual | Admin, Asistente |
| CU15 | Registrar histórico de consumo | Sistema |
| CU16 | Generar curva de consumo | Administrador |
| CU17 | Proyectar fecha y cantidad de recompra | Administrador |

```plantuml
@startuml
title Subsistema: Gestión de Inventario de Materia Prima

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Sistema\n(Automático)" as Sistema

rectangle "Gestión de Inventario de Materia Prima" {
    (CU07: Registrar\nmateria prima) as CU07
    (CU08: Consultar\nmateria prima) as CU08
    (CU09: Actualizar\nmateria prima) as CU09
    (CU10: Eliminar\nmateria prima) as CU10
    (CU11: Registrar\nentrada de MP) as CU11
    (CU12: Registrar\nsalida de MP) as CU12
    (CU13: Aplicar\nmetodología PEPS) as CU13
    (CU14: Consultar\nstock actual) as CU14
    (CU15: Registrar\nhistórico de consumo) as CU15
    (CU16: Generar curva\nde consumo) as CU16
    (CU17: Proyectar fecha\ny cantidad de recompra) as CU17
}

Admin --> CU07
Admin --> CU08
Admin --> CU09
Admin --> CU10
Admin --> CU11
Admin --> CU12
Admin --> CU14
Admin --> CU16
Admin --> CU17

Asistente --> CU08
Asistente --> CU11
Asistente --> CU12
Asistente --> CU14

Sistema --> CU13
Sistema --> CU15

CU12 ..> CU13 : <<include>>
CU12 ..> CU15 : <<include>>
CU16 ..> CU15 : <<include>>
CU17 ..> CU15 : <<include>>
CU09 ..> CU13 : <<extend>>

note right of CU13
  Garantiza que los lotes
  más antiguos se consuman
  primero (PEPS).
end note

note right of CU17
  Basado en el histórico
  de consumo, estima cuándo
  y cuánto recomprar.
end note

@enduml
```

---

## 4. Subsistema: Gestión de Alertas
**Casos de uso:** CU18 al CU21

| Código | Nombre | Actor |
|--------|--------|-------|
| CU18 | Generar alerta de stock bajo | Sistema |
| CU19 | Generar alerta de vencimiento próximo | Sistema |
| CU20 | Generar alerta de variación de precio de MP | Sistema |
| CU21 | Visualizar alertas en dashboard | Admin, Asistente |

```plantuml
@startuml
title Subsistema: Gestión de Alertas

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Sistema\n(Automático)" as Sistema

rectangle "Gestión de Alertas" {
    (CU18: Alerta de\nstock bajo) as CU18
    (CU19: Alerta de\nvencimiento próximo) as CU19
    (CU20: Alerta de variación\nde precio de MP) as CU20
    (CU21: Visualizar alertas\nen dashboard) as CU21
}

Sistema --> CU18
Sistema --> CU19
Sistema --> CU20

Admin --> CU21
Asistente --> CU21

CU18 ..> CU21 : <<extend>>
CU19 ..> CU21 : <<extend>>
CU20 ..> CU21 : <<extend>>

note right of CU18
  Se activa cuando el stock
  baja del mínimo definido.
end note

note right of CU19
  Se activa cuando un lote
  está próximo a vencer
  (por defecto: 30 días).
end note

note right of CU20
  Se activa cuando el precio
  de una MP varía más del
  umbral configurado.
end note

@enduml
```

---

## 5. Subsistema: Gestión de Producto Terminado
**Casos de uso:** CU22 al CU25

| Código | Nombre | Actor |
|--------|--------|-------|
| CU22 | Registrar entrada de producto terminado | Admin, Asistente |
| CU23 | Registrar salida de producto terminado | Admin, Asistente |
| CU24 | Consultar stock por producto y bodega | Admin, Asistente |
| CU25 | Consultar disponibilidad para comerciales | Admin, Asistente, Comercial |

```plantuml
@startuml
title Subsistema: Gestión de Producto Terminado

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Comercial\n(Vendedor)" as Comercial

rectangle "Gestión de Producto Terminado" {
    (CU22: Registrar entrada\nde producto terminado) as CU22
    (CU23: Registrar salida\nde producto terminado) as CU23
    (CU24: Consultar stock\npor producto y bodega) as CU24
    (CU25: Consultar disponibilidad\npara comerciales) as CU25
}

Admin --> CU22
Admin --> CU23
Admin --> CU24
Admin --> CU25

Asistente --> CU22
Asistente --> CU23
Asistente --> CU24
Asistente --> CU25

Comercial --> CU25

CU25 ..> CU24 : <<include>>

note right of CU25
  Permite al comercial
  consultar en tiempo real
  qué hay disponible en
  cada bodega para cerrar
  una venta.
end note

@enduml
```

---

## 6. Subsistema: Gestión de Formulaciones
**Casos de uso:** CU26 al CU29

| Código | Nombre | Actor |
|--------|--------|-------|
| CU26 | Registrar formulación | Administrador |
| CU27 | Consultar formulación | Administrador |
| CU28 | Actualizar formulación | Administrador |
| CU29 | Eliminar formulación | Administrador |

```plantuml
@startuml
title Subsistema: Gestión de Formulaciones

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin

rectangle "Gestión de Formulaciones" {
    (CU26: Registrar\nformulación) as CU26
    (CU27: Consultar\nformulación) as CU27
    (CU28: Actualizar\nformulación) as CU28
    (CU29: Eliminar\nformulación) as CU29
}

Admin --> CU26
Admin --> CU27
Admin --> CU28
Admin --> CU29

CU26 ..> CU28 : <<extend>>

note right of CU26
  Cada formulación define
  las materias primas y
  cantidades para fabricar
  un producto.
end note

note right of CU28
  Al actualizar, el sistema
  recalcula automáticamente
  el costo del producto.
end note

@enduml
```

---

## 7. Subsistema: Gestión de Costos y Precios
**Casos de uso:** CU30 al CU38

| Código | Nombre | Actor |
|--------|--------|-------|
| CU30 | Calcular costo de producción | Sistema |
| CU31 | Consultar histórico de costos | Administrador |
| CU32 | Registrar precio de venta | Administrador |
| CU33 | Consultar lista de precios | Admin, Asistente |
| CU34 | Actualizar precio manualmente | Administrador |
| CU35 | Configurar umbral de variación | Administrador |
| CU36 | Detectar variación de costo superior al umbral | Sistema |
| CU37 | Actualizar precio automáticamente | Sistema |
| CU38 | Notificar cambio de precio | Sistema |

```plantuml
@startuml
title Subsistema: Gestión de Costos y Precios

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente
actor "Sistema\n(Automático)" as Sistema

rectangle "Gestión de Costos y Precios" {
    (CU30: Calcular costo\nde producción) as CU30
    (CU31: Consultar histórico\nde costos) as CU31
    (CU32: Registrar precio\nde venta) as CU32
    (CU33: Consultar lista\nde precios) as CU33
    (CU34: Actualizar precio\nmanualmente) as CU34
    (CU35: Configurar umbral\nde variación) as CU35
    (CU36: Detectar variación\nsuperior al umbral) as CU36
    (CU37: Actualizar precio\nautomáticamente) as CU37
    (CU38: Notificar cambio\nde precio) as CU38
}

Admin --> CU31
Admin --> CU32
Admin --> CU33
Admin --> CU34
Admin --> CU35

Asistente --> CU33

Sistema --> CU30
Sistema --> CU36
Sistema --> CU37
Sistema --> CU38

CU30 ..> CU36 : <<include>>
CU36 ..> CU37 : <<include>>
CU37 ..> CU38 : <<include>>

note right of CU35
  Por defecto el umbral
  es 3%. Si el costo sube
  o baja más de ese %, el
  precio se actualiza solo.
end note

note right of CU37
  Precio nuevo =
  Costo × (1 + cif %)
end note

@enduml
```

---

## 8. Subsistema: Gestión de Órdenes de Producción
**Casos de uso:** CU39 al CU42

| Código | Nombre | Actor |
|--------|--------|-------|
| CU39 | Registrar orden de producción | Administrador |
| CU40 | Consultar orden de producción | Admin, Asistente |
| CU41 | Actualizar estado de orden | Admin, Asistente |
| CU42 | Asociar consumo de MP a la orden | Admin, Asistente |

```plantuml
@startuml
title Subsistema: Gestión de Órdenes de Producción

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente

rectangle "Gestión de Órdenes de Producción" {
    (CU39: Registrar orden\nde producción) as CU39
    (CU40: Consultar orden\nde producción) as CU40
    (CU41: Actualizar estado\nde la orden) as CU41
    (CU42: Asociar consumo\nde MP a la orden) as CU42
}

Admin --> CU39
Admin --> CU40
Admin --> CU41
Admin --> CU42

Asistente --> CU40
Asistente --> CU41
Asistente --> CU42

CU39 ..> CU42 : <<include>>
CU42 ..> CU12 : <<include>>

note right of CU41
  Estados posibles:
  Pendiente → En proceso
  → Finalizada / Cancelada
end note

note right of CU42
  Al asociar el consumo,
  se descuenta automáticamente
  la materia prima del
  inventario usando PEPS.
end note

@enduml
```

---

## 9. Subsistema: Gestión de Códigos QR
**Casos de uso:** CU43 al CU46

| Código | Nombre | Actor |
|--------|--------|-------|
| CU43 | Generar código QR por referencia de producto | Administrador |
| CU44 | Asociar documentación técnica | Administrador |
| CU45 | Actualizar documentos asociados | Administrador |
| CU46 | Visualizar documentación desde QR | Cliente, Comercial |

```plantuml
@startuml
title Subsistema: Gestión de Códigos QR

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Comercial\n(Vendedor)" as Comercial
actor "Cliente\n(Externo)" as Cliente

rectangle "Gestión de Códigos QR" {
    (CU43: Generar código QR\npor referencia) as CU43
    (CU44: Asociar documentación\ntécnica al QR) as CU44
    (CU45: Actualizar documentos\nasociados) as CU45
    (CU46: Visualizar documentación\ndesde el QR) as CU46
}

Admin --> CU43
Admin --> CU44
Admin --> CU45

Comercial --> CU46
Cliente --> CU46

CU43 ..> CU44 : <<include>>
CU46 ..> CU44 : <<include>>

note right of CU44
  Documentos asociados:
  - Ficha técnica
  - Ficha de seguridad
  - Certificado de calidad
end note

note right of CU46
  Al escanear el QR del
  envase, se abre la
  documentación del producto.
end note

@enduml
```

---

## 10. Subsistema: Reportes y Analíticas
**Casos de uso:** CU47 al CU53

| Código | Nombre | Actor |
|--------|--------|-------|
| CU47 | Generar reporte de consumo de materia prima | Administrador |
| CU48 | Generar reporte de volumen de producción por período | Administrador |
| CU49 | Generar reporte de variación de costos | Administrador |
| CU50 | Visualizar dashboard de indicadores | Admin, Asistente |
| CU51 | Exportar datos de ventas para Power BI | Administrador |
| CU52 | Exportar datos de producción para Power BI | Administrador |
| CU53 | Exportar datos de cartera para Power BI | Administrador |

```plantuml
@startuml
title Subsistema: Reportes y Analíticas

left to right direction

skinparam backgroundColor #FFFFFF
skinparam defaultFontName Arial
skinparam defaultFontSize 12
skinparam actor {
    BackgroundColor #D6EAF8
    BorderColor #1A5276
    FontStyle bold
}
skinparam usecase {
    BackgroundColor #FEF9E7
    BorderColor #B7950B
}

actor "Administrador" as Admin
actor "Asistente de\nProducción" as Asistente

rectangle "Reportes y Analíticas" {
    (CU47: Reporte de consumo\nde materia prima) as CU47
    (CU48: Reporte de volumen\nde producción) as CU48
    (CU49: Reporte de variación\nde costos) as CU49
    (CU50: Dashboard de\nindicadores) as CU50
    (CU51: Exportar datos\nde ventas) as CU51
    (CU52: Exportar datos\nde producción) as CU52
    (CU53: Exportar datos\nde cartera) as CU53
}

Admin --> CU47
Admin --> CU48
Admin --> CU49
Admin --> CU50
Admin --> CU51
Admin --> CU52
Admin --> CU53

Asistente --> CU50

CU50 ..> CU21 : <<include>>

note right of CU50
  Muestra en tiempo real:
  stock, alertas activas,
  consumo, producción
  y variación de costos.
end note

note right of CU51
  Exporta CSV/Excel
  compatible con Power BI
  para reducir el proceso
  manual actual.
end note

@enduml
```

---

## Resumen General

| Subsistema | Casos de Uso | Total |
|------------|--------------|-------|
| Gestión de Usuarios y Permisos | CU01 — CU06 | 6 |
| Gestión de Inventario de Materia Prima | CU07 — CU17 | 11 |
| Gestión de Alertas | CU18 — CU21 | 4 |
| Gestión de Producto Terminado | CU22 — CU25 | 4 |
| Gestión de Formulaciones | CU26 — CU29 | 4 |
| Gestión de Costos y Precios | CU30 — CU38 | 9 |
| Gestión de Órdenes de Producción | CU39 — CU42 | 4 |
| Gestión de Códigos QR | CU43 — CU46 | 4 |
| Reportes y Analíticas | CU47 — CU53 | 7 |
| **TOTAL** | | **53** |

---

## Cómo generar las imágenes PNG

### Opción rápida — PlantUML Online
1. Entra a **www.plantuml.com/plantuml**
2. Copia el código de cada diagrama (entre `@startuml` y `@enduml`)
3. El sitio genera la imagen automáticamente
4. Descarga como PNG

### Opción desde VS Code
1. Instala la extensión **PlantUML** de jebbs
2. Crea un archivo `.puml` y pega el código
3. Presiona `Alt + D` para previsualizar
4. Clic derecho → Export → PNG