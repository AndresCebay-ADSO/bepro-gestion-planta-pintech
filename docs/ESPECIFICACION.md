| **NOMBRE:** | CU01 - Iniciar sesión |
| --- | --- |
| **ACTORES:** | Todos los actores |
| **DESCRIPCIÓN:** | Permite a los usuarios autenticarse en el sistema mediante credenciales. El acceso otorgado dependerá del rol asignado. |
| **PRECONDICIONES:** | El usuario debe estar registrado en el sistema. |
| **FLUJO 		NORMAL:** | 1. El actor accede a la página de inicio de sesión.
2. Ingresa usuario y contraseña.
3. El sistema valida las credenciales.
4. Si son correctas, el sistema inicia la sesión y redirige al dashboard correspondiente según el rol.
5. Si son incorrectas, muestra mensaje de error y permite reintentar. |
| **FLUJOS 		ALTERNOS** | **A. Usuario inactivo:** Si el usuario ha sido desactivado, el sistema muestra mensaje "Usuario no autorizado". |
| **POSTCONDICIÓN** | Se registra el inicio de sesión en los logs. |

| **NOMBRE:** | CU02 -  Registrar usuario |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador crear un nuevo usuario en el sistema, asignándole credenciales y un rol inicial. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El correo o usuario no existe previamente en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de usuarios. 
2. Selecciona "Registrar usuario". 
3. Ingresa nombre, correo, contraseña temporal y rol. 
4. El sistema valida que el correo no esté duplicado. 
5. El sistema crea el usuario y lo almacena. 
6. Muestra mensaje de confirmación. |
| **FLUJOS 		ALTERNOS** | A. Correo duplicado: el sistema muestra error "El correo ya está registrado" y no permite continuar. B. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | El usuario queda registrado y puede iniciar sesión con sus credenciales. |

| **NOMBRE:** | CU03 – Consultar usuario |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador consultar la información de los usuarios registrados en el sistema, incluyendo nombre, rol y estado. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. Existen usuarios registrados en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de usuarios. 
2. El sistema muestra la lista de usuarios registrados. 
3. El administrador puede buscar por nombre, correo o rol. 
4. El sistema filtra y muestra los resultados. |
| **FLUJOS 		ALTERNOS** | A. Sin resultados: el sistema muestra "No se encontraron usuarios con los criterios ingresados". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU04 – Actualizar usuario |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador modificar la información de un usuario existente, como nombre, correo o estado activo/inactivo. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El usuario existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de usuarios. 
2. Selecciona el usuario a modificar. 
3. Edita los campos requeridos. 
4. El sistema valida los datos ingresados. 
5. Guarda los cambios y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Correo duplicado: si el nuevo correo ya pertenece a otro usuario, el sistema muestra error. 
B. Campos inválidos: el sistema resalta los campos con formato incorrecto. |
| **POSTCONDICIÓN** | La información del usuario queda actualizada en el sistema. |

| **NOMBRE:** | CU05 – Eliminar usuario |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador desactivar o eliminar un usuario del sistema. Por seguridad, se recomienda desactivación lógica en lugar de eliminación física. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El usuario existe y no es el administrador principal del sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de usuarios. 
2. Selecciona el usuario a eliminar. 
3. Selecciona "Eliminar" o "Desactivar". 
4. El sistema solicita confirmación. 
5. El administrador confirma. 
6. El sistema desactiva al usuario y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Usuario con registros activos: el sistema advierte que el usuario tiene actividad registrada y recomienda desactivación en lugar de eliminación. 
B. Intento de eliminar administrador principal: el sistema bloquea la acción y muestra mensaje de restricción. |
| **POSTCONDICIÓN** | El usuario queda inactivo y no puede iniciar sesión. Sus registros históricos se conservan. |

| **NOMBRE:** | CU06 – Asignar rol y permisos |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador asignar un rol a un usuario (Administrador, Asistente de Producción o Comercial) y, si es necesario, ajustar permisos específicos. |
| **PRECONDICIONES:** | El usuario existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de usuarios.
2. Selecciona un usuario y elige "Asignar rol".
3. Selecciona el rol deseado.
4. El sistema muestra los permisos asociados a ese rol.
5. El administrador confirma la asignación.
6. El sistema actualiza el rol del usuario y guarda los permisos. |
| **FLUJOS 		ALTERNOS** | A. Rol ya asignado: el sistema notifica que el usuario ya tiene ese rol y solicita confirmación para reemplazarlo. |
| **POSTCONDICIÓN** | El usuario tiene un nuevo rol activo. Su acceso se rige por la nueva configuración inmediatamente. |

| **NOMBRE:** | CU07 – Registrar materia prima |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite registrar una nueva materia prima en el sistema con toda su información asociada. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. La materia prima no existe previamente en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de inventario de materia prima. 
2. Selecciona "Registrar materia prima". 
3. Ingresa código, unidad de medida, precio actual, stock inicial y fecha de vencimiento. 
4. El sistema valida los datos. 
5. Guarda el registro y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Materia prima duplicada: el sistema muestra error "La materia prima ya existe" y no permite continuar. 
B. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | La materia prima queda registrada y disponible para ser usada en formulaciones y movimientos de inventario. |

| **NOMBRE:** | CU08 – Consultar materia prima |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar la información de las materias primas registradas, incluyendo stock actual, precio y fecha de vencimiento.
 |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. Existen materias primas registradas. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de inventario de materia prima. 
2. El sistema muestra la lista de materias primas. 
3. El usuario puede buscar por código o categoría. 
4. El sistema filtra y muestra los resultados con su información completa. |
| **FLUJOS 		ALTERNOS** | A. Sin resultados: el sistema muestra "No se encontraron materias primas con los criterios ingresados". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU09 – Actualizar materia prima |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite modificar la información de una materia prima existente, como precio, unidad de medida o fecha de vencimiento. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. La materia prima existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de inventario de materia prima. 
2. Selecciona la materia prima a modificar. 
3. Edita los campos requeridos. 
4. El sistema valida los datos. 
5. Guarda los cambios y muestra confirmación. 
6. Si el precio cambió, el sistema activa CU30 para recalcular costos afectados. |
| **FLUJOS 		ALTERNOS** | A. Campos inválidos: el sistema resalta los campos con formato incorrecto. 
B. Precio modificado sin formulaciones asociadas: el sistema guarda el cambio sin disparar recálculo de costos. |
| **POSTCONDICIÓN** | La información de la materia prima queda actualizada. Si el precio cambió, los costos de productos asociados se recalculan automáticamente. |

| **NOMBRE:** | CU10 – Eliminar materia prima |
| --- | --- |
| **ACTORES:** | Administrador  |
| **DESCRIPCIÓN:** | Permite desactivar o eliminar una materia prima del sistema. Se recomienda desactivación lógica si la materia prima tiene historial de movimientos. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. La materia prima existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de inventario de materia prima. 
2. Selecciona la materia prima a eliminar. 
3. El sistema verifica si tiene movimientos o formulaciones asociadas. 
4. El sistema solicita confirmación. 
5. El administrador confirma. 
6. El sistema desactiva la materia prima y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Materia prima con formulaciones activas: el sistema advierte que está siendo usada en formulaciones y recomienda desactivación. 
B. Materia prima con stock disponible: el sistema advierte que aún hay stock registrado antes de proceder. |
| **POSTCONDICIÓN** | La materia prima queda inactiva. Sus registros históricos se conservan para trazabilidad. |

| **NOMBRE:** | CU11 – Registrar entrada de materia prima |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite registrar el ingreso de un lote de materia prima al inventario, incluyendo cantidad, fecha de entrada y fecha de vencimiento del lote. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. La materia prima existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de inventario de materia prima. 
2. Selecciona "Registrar entrada". 
3. Selecciona la materia prima e ingresa cantidad, fecha de entrada y fecha de vencimiento del lote. 
4. El sistema valida los datos. 
5. Crea el lote y actualiza el stock total. 6. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Fecha de vencimiento anterior a la fecha actual: el sistema advierte que el lote ingresado ya está vencido y solicita confirmación para proceder. 
B. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | El lote queda registrado. El stock total de la materia prima se actualiza. El lote queda disponible para ser consumido según metodología PEPS. |

| **NOMBRE:** | CU12 – Registrar salida de materia prima |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite registrar el consumo de materia prima. El sistema aplica automáticamente la metodología PEPS para descontar los lotes más antiguos. |
| **PRECONDICIONES:** | La materia prima está registrada. Existe stock suficiente. |
| **FLUJO 		NORMAL:** | 1. El actor accede al módulo de inventario. 
2. Selecciona "Registrar salida". 
3. Ingresa materia prima, cantidad y referencia de orden (opcional). 
4. El sistema verifica stock suficiente. 
5. El sistema aplica PEPS (CU13). 
6. Actualiza el stock y registra el movimiento. 
7. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Stock insuficiente: el sistema muestra error y no permite completar la operación. 
B. Sin orden asociada: el sistema permite registrar como consumo general. |
| **POSTCONDICIÓN** | Inventario actualizado. Trazabilidad del consumo registrada por lote. |

| **NOMBRE:** | CU13 – Aplicar metodología PEPS |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Caso de uso incluido por CU12. Garantiza que los lotes más antiguos se consuman primero. |
| **PRECONDICIONES:** | Existen lotes registrados con cantidades y fechas de entrada. |
| **FLUJO 		NORMAL:** | 1. Se recibe solicitud de consumo de X cantidad. 
2. El sistema ordena lotes por fecha ascendente. 
3. Itera: si el lote tiene cantidad >= restante, descuenta y termina. Si no, descuenta todo el lote y continúa al siguiente. 
4. Stock actualizado por lote al finalizar. |
| **FLUJOS 		ALTERNOS** | A. Stock total insuficiente: el sistema interrumpe y retorna error a CU12. |
| **POSTCONDICIÓN** | Lotes actualizados. Consumo asignado por lote registrado para trazabilidad. |

| **NOMBRE:** | CU14 – Consultar stock actual |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar el stock disponible de cada materia prima, discriminado por lotes con sus respectivas fechas de entrada y vencimiento. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de inventario de materia prima. 
2. Selecciona "Consultar stock". 
3. El sistema muestra el listado de materias primas con su stock total y detalle por lote. 4. El usuario puede filtrar por materia prima o estado del lote. |
| **FLUJOS 		ALTERNOS** | A. Sin stock disponible: el sistema indica que la materia prima tiene stock en cero. 
B. Lotes próximos a vencer: el sistema resalta visualmente los lotes con vencimiento próximo. |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU15 – Registrar histórico de consumo |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Caso de uso incluido por CU12. Cada vez que se registra una salida de materia prima, el sistema almacena el registro en el histórico de consumo para alimentar las curvas de análisis. |
| **PRECONDICIONES:** | Se ha completado exitosamente un registro de salida de materia prima (CU12). |
| **FLUJO 		NORMAL:** | 1. El sistema recibe los datos del movimiento de salida. 
2. Registra la fecha, materia prima, cantidad consumida y referencia de orden si aplica. 3. Almacena el registro en la tabla de histórico de consumo. |
| **FLUJOS 		ALTERNOS** | A. Error de almacenamiento: el sistema registra el error en los logs y notifica al administrador. |
| **POSTCONDICIÓN** | El movimiento queda registrado en el histórico y disponible para análisis de curvas de consumo. |

| **NOMBRE:** | CU16 – Generar curva de consumo |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite visualizar el comportamiento histórico del consumo de una materia prima a lo largo del tiempo, mostrando tendencias y patrones de uso. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. Existen registros históricos de consumo para la materia prima seleccionada. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de inventario o reportes. 
2. Selecciona una materia prima. 
3. Define el período de análisis. 
4. El sistema procesa el histórico de consumo. 
5. Muestra una gráfica de consumo en el tiempo con sus valores acumulados y promedios. |
| **FLUJOS 		ALTERNOS** | A. Sin historial suficiente: el sistema muestra mensaje "No hay suficientes datos para generar la curva en el período seleccionado". |
| **POSTCONDICIÓN** | No hay cambios en los datos. La curva queda disponible para exportación. |

| **NOMBRE:** | CU17 – Proyectar fecha y cantidad de re-compra |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Con base en el histórico de consumo y el stock actual, el sistema proyecta cuándo se agotará la materia prima y sugiere una fecha y cantidad óptima de recompra. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. Existen registros históricos de consumo. La materia prima tiene stock actual registrado. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de inventario. 2. Selecciona una materia prima. 3. El sistema calcula el promedio de consumo por período. 4. Estima los días de stock restante dividiendo stock actual entre consumo promedio. 5. Sugiere fecha de recompra y cantidad recomendada basada en el historial. 6. Muestra la proyección al administrador. |
| **FLUJOS 		ALTERNOS** | A. Consumo irregular: si el historial muestra consumo muy variable, el sistema advierte que la proyección puede tener baja precisión. B. Sin historial: el sistema indica que no hay datos suficientes para proyectar. |
| **POSTCONDICIÓN** | No hay cambios en los datos. La proyección queda disponible para consulta. |

| **NOMBRE:** | CU18 – Generar alerta de stock bajo |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | El sistema genera automáticamente una alerta cuando el stock de una materia prima cae por debajo del nivel mínimo definido por el administrador. |
| **PRECONDICIONES:** | La materia prima tiene un nivel mínimo de stock configurado. Se ha registrado una salida de materia prima (CU12). |
| **FLUJO 		NORMAL:** | 1. Después de cada registro de salida, el sistema verifica el stock actual de la materia prima afectada. 
2. Compara el stock actual con el nivel mínimo configurado. 
3. Si el stock actual es menor o igual al mínimo, genera una alerta. 
4. La alerta queda registrada y visible en el dashboard de alertas. 
5. Se notifica al Administrador y Asistente de Producción. |
| **FLUJOS 		ALTERNOS** | A. Sin nivel mínimo configurado: el sistema no genera alerta y registra en logs que la materia prima no tiene umbral definido. |
| **POSTCONDICIÓN** | La alerta queda registrada en el sistema y visible para los usuarios habilitados hasta que sea resuelta. |

| **NOMBRE:** | CU19 – Generar alerta de vencimiento próximo |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | El sistema genera automáticamente una alerta cuando un lote de materia prima está próximo a vencer, según un margen de días configurable. |
| **PRECONDICIONES:** | Existen lotes de materia prima con fecha de vencimiento registrada. El margen de días de anticipación está configurado. |
| **FLUJO 		NORMAL:** | 1. El sistema ejecuta una verificación periódica de fechas de vencimiento. 
2. Compara la fecha de vencimiento de cada lote con la fecha actual más el margen configurado. 
3. Si un lote vence dentro del margen, genera una alerta. 
4. La alerta queda registrada y visible en el dashboard. 
5. Se notifica al Administrador y Asistente de Producción. |
| **FLUJOS 		ALTERNOS** | A. Lote ya vencido: el sistema genera una alerta de mayor urgencia indicando que el lote ya está vencido. 
B. Sin margen configurado: el sistema usa un valor por defecto de 30 días. |
| **POSTCONDICIÓN** | La alerta queda registrada. El lote afectado queda marcado visualmente en el inventario. |

| **NOMBRE:** | CU20 – Generar alerta de variación de precio de materia prima |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | El sistema genera una alerta cuando el precio de una materia prima cambia de forma significativa respecto al precio anterior registrado. |
| **PRECONDICIONES:** | La materia prima tiene un precio anterior registrado. Se ha actualizado el precio de la materia prima (CU09). |
| **FLUJO 		NORMAL:** | 1. Al actualizar el precio de una materia prima, el sistema calcula la variación porcentual respecto al precio anterior. 
2. Si la variación supera el umbral configurado, genera una alerta. 
3. La alerta indica el porcentaje de variación, precio anterior y precio nuevo. 
4. Queda registrada en el dashboard de alertas. 
5. Se notifica al Administrador. |
| **FLUJOS 		ALTERNOS** | A. Variación dentro del umbral: el sistema actualiza el precio sin generar alerta. B. Sin precio anterior: el sistema registra el precio como inicial sin generar alerta. |
| **POSTCONDICIÓN** | La alerta queda registrada. El sistema activa CU30 para recalcular costos de productos que usan esta materia prima. |

| **NOMBRE:** | CU21 – Visualizar alertas en dashboard |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite visualizar en tiempo real todas las alertas activas del sistema, clasificadas por tipo y urgencia.
 |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. Existen alertas registradas en el sistema. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al dashboard o módulo de alertas. 
2. El sistema muestra las alertas activas clasificadas por tipo: stock bajo, vencimiento próximo, variación de precio. 
3. Cada alerta muestra la materia prima afectada, fecha de generación y nivel de urgencia. 
4. El usuario puede marcar una alerta como resuelta. 
5. El sistema actualiza el estado de la alerta. |
| **FLUJOS 		ALTERNOS** | A. Sin alertas activas: el sistema muestra "No hay alertas activas en este momento". 
B. Alerta ya resuelta: el sistema mueve la alerta al histórico de alertas resueltas. |
| **POSTCONDICIÓN** | Las alertas marcadas como resueltas se archivan en el histórico. El dashboard muestra únicamente alertas activas. |

| **NOMBRE:** | CU22 – Registrar entrada de producto terminado |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite registrar el ingreso de producto terminado al inventario de una bodega específica, tras completar un proceso de producción. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. El producto existe en el sistema. La bodega de destino está registrada. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de producto terminado. 
2. Selecciona "Registrar entrada". 
3. Selecciona el producto, cantidad, bodega de destino y fecha. 
4. El sistema valida los datos ingresados. 
5. Actualiza el stock del producto en la bodega indicada. 
6. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Producto no existe: el sistema muestra error y no permite continuar. 
B. Bodega no registrada: el sistema muestra error indicando que debe registrar la bodega primero. 
C. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | El stock del producto terminado en la bodega queda actualizado. El movimiento queda registrado para trazabilidad. |

| **NOMBRE:** | CU23 - Registrar salida de producto terminado |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite registrar el despacho o salida de producto terminado de una bodega, disminuyendo el stock disponible. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. El producto existe en la bodega con stock suficiente. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de producto terminado. 
2. Selecciona "Registrar salida". 
3. Selecciona el producto, bodega, cantidad y fecha de despacho. 
4. El sistema verifica que el stock disponible sea suficiente. 
5. Descuenta la cantidad del stock de la bodega. 
6. Registra el movimiento y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Stock insuficiente: el sistema muestra error "Stock insuficiente en la bodega seleccionada" y no permite continuar. 
B. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | El stock del producto en la bodega queda actualizado. El movimiento queda registrado para trazabilidad. |

| **NOMBRE:** | CU24 – Consultar stock por producto y bodega |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar el inventario de producto terminado discriminado por bodega, mostrando el stock disponible de cada referencia en cada ubicación. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. Existen productos registrados con stock en al menos una bodega. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de producto terminado. 
2. Selecciona "Consultar stock". 
3. Puede filtrar por producto, categoría o bodega. 
4. El sistema muestra una tabla con el stock disponible por producto y bodega. 
5. El usuario puede exportar el resultado opcionalmente. |
| **FLUJOS 		ALTERNOS** | A. Sin stock: el sistema indica que el producto seleccionado no tiene stock disponible en ninguna bodega. 
B. Sin resultados con filtros: el sistema muestra "No se encontraron productos con los criterios ingresados". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU25 – Consultar disponibilidad para comerciales |
| --- | --- |
| **ACTORES:** | Comercial, Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar en tiempo real el stock de producto terminado por bodega para facilitar el cierre de ventas. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con cualquier rol. |
| **FLUJO 		NORMAL:** | 1. El actor accede al módulo de producto terminado. 
2. Selecciona "Consultar disponibilidad". 
3. Filtra por referencia, categoría o bodega. 
4. El sistema muestra tabla con stock actual por bodega. 
5. El actor puede exportar la consulta opcionalmente. |
| **FLUJOS 		ALTERNOS** | A. Sin resultados: el sistema muestra "No se encontraron productos". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU26 – Registrar formulación |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite registrar una nueva formulación de producción asociada a un producto, especificando las materias primas requeridas y sus cantidades. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El producto existe en el sistema. Las materias primas a incluir están registradas. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de formulaciones. 
2. Selecciona "Registrar formulación". 
3. Selecciona el producto al que se asociará la formulación. 
4. Agrega las materias primas con sus cantidades y unidades de medida. 
5. El sistema valida que todas las materias primas existan. 
6. Guarda la formulación como activa para ese producto. 
7. El sistema activa CU30 para calcular el costo con la nueva formulación. 
8. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Producto ya tiene formulación activa: el sistema advierte que existe una formulación activa y pregunta si desea reemplazarla o crear una versión nueva. 
B. Materia prima no registrada: el sistema muestra error indicando que debe registrar primero la materia prima. 
C. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | La formulación queda registrada y activa para el producto. El costo del producto se recalcula automáticamente. |

| **NOMBRE:** | CU27 – Consultar formulación |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite consultar las formulaciones registradas en el sistema, incluyendo las materias primas que las componen, sus cantidades y el costo calculado. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. Existen formulaciones registradas en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de formulaciones. 
2. El sistema muestra la lista de productos con formulaciones registradas. 
3. El administrador selecciona un producto. 
4. El sistema muestra el detalle de la formulación activa: materias primas, cantidades, unidades y costo calculado. 
5. El administrador puede consultar versiones anteriores si existen. |
| **FLUJOS 		ALTERNOS** | A. Sin formulación registrada: el sistema indica que el producto no tiene formulación activa. 
B. Sin versiones anteriores: el sistema indica que solo existe la versión actual. |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU28 – Actualizar formulación |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite modificar una formulación existente, agregando, eliminando o cambiando las cantidades de las materias primas que la componen. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. La formulación existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de formulaciones. 
2. Selecciona el producto y su formulación activa. 
3. Modifica las materias primas o cantidades según sea necesario. 
4. El sistema valida los datos ingresados. 
5. Guarda la formulación actualizada como nueva versión activa. 
6. El sistema activa CU30 para recalcular el costo con la formulación actualizada. 
7. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Materia prima no registrada: el sistema muestra error si se intenta agregar una materia prima inexistente. 
B. Cantidad inválida: el sistema resalta los campos con valores negativos o en cero. |
| **POSTCONDICIÓN** | La formulación queda actualizada como versión activa. La versión anterior se conserva en el historial. El costo del producto se recalcula automáticamente. |

| **NOMBRE:** | CU29 – Eliminar formulación |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite desactivar o eliminar una formulación del sistema. Se recomienda desactivación lógica para conservar el historial de costos. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. La formulación existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de formulaciones. 
2. Selecciona el producto y su formulación. 
3. Selecciona "Eliminar formulación". 
4. El sistema verifica si la formulación está siendo usada en órdenes de producción activas. 
5. El sistema solicita confirmación. 
6. El administrador confirma. 
7. El sistema desactiva la formulación y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Formulación con órdenes activas: el sistema advierte que la formulación está asociada a órdenes en curso y recomienda no eliminarla hasta completarlas. 
B. Única formulación del producto: el sistema advierte que el producto quedará sin formulación activa y no podrá calcular costos. |
| **POSTCONDICIÓN** | La formulación queda inactiva. Su historial se conserva para trazabilidad y consulta de costos históricos. |

| **NOMBRE:** | CU30 – Calcular costo de producción |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Calcula el costo de producción de un producto con base en su formulación activa y los precios actuales de materias primas. Se dispara automáticamente ante cambios en formulaciones o precios. |
| **PRECONDICIONES:** | Existe formulación activa. Las materias primas tienen precio registrado. |
| **FLUJO 		NORMAL:** | 1. Se dispara el evento (cambio en formulación o precio de MP). 
2. El sistema obtiene la formulación activa. 
3. Multiplica cantidad por precio unitario de cada materia prima. 
4. Suma costos parciales. 
5. Almacena el costo con fecha. 
6. Si la variación supera el umbral, activa CU37. |
| **FLUJOS 		ALTERNOS** | A. Materia prima sin precio: el cálculo no se completa y se genera alerta al administrador. |
| **POSTCONDICIÓN** | Nuevo costo registrado en histórico. Si aplica, precio de venta actualizado automáticamente. |

| **NOMBRE:** | CU31 – Consultar histórico de costos |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite consultar el historial de costos de producción de un producto, mostrando la evolución del costo a lo largo del tiempo según los cambios en formulaciones y precios de materias primas. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El producto tiene al menos un costo registrado en el histórico. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de costos. 2. Selecciona un producto. 3. El sistema muestra el historial de costos con fecha, valor y causa del cambio. 4. El administrador puede filtrar por período. 5. El sistema permite exportar el histórico opcionalmente. |
| **FLUJOS 		ALTERNOS** | A. Sin historial: el sistema indica que el producto no tiene costos registrados aún. B. Sin resultados en el período: el sistema muestra "No hay registros de costo para el período seleccionado". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU32 – Registrar precio de venta |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite registrar el precio de venta de un producto, aplicando un margen de ganancia sobre el costo calculado. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El producto existe y tiene un costo calculado. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de costos y precios. 
2. Selecciona un producto. 
3. Ingresa el margen de ganancia porcentual deseado. 
4. El sistema calcula automáticamente el precio de venta: precio = costo × (1 + margen). 
5. El administrador revisa y confirma el precio. 
6. El sistema registra el precio de venta y lo agrega a la lista de precios. 
7. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Producto sin costo calculado: el sistema advierte que no puede calcular el precio hasta que exista un costo registrado. 
B. Margen inválido: el sistema resalta el campo si el margen ingresado es negativo o no numérico. |
| **POSTCONDICIÓN** | El precio de venta queda registrado y disponible en la lista de precios para consulta del área de producción. |

| **NOMBRE:** | CU33 – Consultar lista de precios |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar la lista de precios de venta actualizada de todos los productos registrados en el sistema. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado. Existen productos con precio de venta registrado. |
| **FLUJO 		NORMAL:** | 1. El usuario accede al módulo de costos y precios. 
2. Selecciona "Consultar lista de precios". 
3. El sistema muestra la lista de productos con su precio de venta actual, costo y margen aplicado. 
4. El usuario puede filtrar por producto o categoría. 
5. El usuario puede exportar la lista opcionalmente. |
| **FLUJOS 		ALTERNOS** | A. Sin precios registrados: el sistema indica que no hay productos con precio de venta definido. 
B. Sin resultados con filtros: el sistema muestra "No se encontraron productos con los criterios ingresados". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU34 – Actualizar precio manualmente |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador modificar manualmente el precio de venta de un producto, independientemente de si hubo una variación de costo. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El producto tiene un precio de venta registrado. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de costos y precios. 
2. Selecciona el producto a modificar. 
3. Ingresa el nuevo precio de venta o el nuevo margen de ganancia. 
4. El sistema calcula el precio resultante si se ingresó margen. 
5. El administrador confirma el cambio. 
6. El sistema actualiza el precio y registra el cambio en el histórico con la fecha y el motivo "Actualización manual". 
7. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Precio inválido: el sistema resalta el campo si el valor ingresado es negativo o no numérico. 
B. Precio igual al anterior: el sistema advierte que el precio ingresado es igual al actual y pregunta si desea continuar. |
| **POSTCONDICIÓN** | El precio de venta queda actualizado en la lista de precios. El cambio queda registrado en el histórico para trazabilidad. |

| **NOMBRE:** | CU35 – Configurar umbral de variación |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador definir el porcentaje de variación de costo a partir del cual el sistema actualizará automáticamente el precio de venta. Por defecto este umbral es del 3%. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. |
| **FLUJO 		NORMAL:** | 1. El administrador accede a la configuración del módulo de costos y precios. 
2. Selecciona "Configurar umbral de variación". 
3. Ingresa el porcentaje deseado. 
4. El sistema valida que el valor sea positivo y numérico. 
5. Guarda la configuración y muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Valor inválido: el sistema resalta el campo si el valor ingresado es negativo, cero o no numérico. 
B. Sin cambios: si el administrador ingresa el mismo valor actual, el sistema no realiza ninguna acción. |
| **POSTCONDICIÓN** | El umbral queda configurado y se aplica a partir de ese momento en todos los cálculos de variación de costo. |

| **NOMBRE:** | CU36 – Detectar variación de costo superior al umbral |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Caso de uso incluido por CU30. Después de recalcular el costo de un producto, el sistema verifica si la variación respecto al costo anterior supera el umbral configurado y determina si debe activarse la actualización automática de precio. |
| **PRECONDICIONES:** | El producto tiene un costo anterior registrado. El umbral de variación está configurado. Se ha completado un re-cálculo de costo (CU30). |
| **FLUJO 		NORMAL:** | 1. El sistema obtiene el costo anterior y el costo nuevo del producto. 
2. Calcula la variación porcentual: ((costo nuevo - costo anterior) / costo anterior) × 100. 
3. Compara la variación con el umbral configurado. 
4. Si la variación supera el umbral, activa CU37. 
5. Si no supera el umbral, registra el nuevo costo sin actualizar el precio. |
| **FLUJOS 		ALTERNOS** | A. Sin costo anterior: el sistema registra el costo como inicial y no realiza comparación. B. Variación negativa: si el costo bajó y supera el umbral, el sistema también activa CU37 para actualizar el precio a la baja. |
| **POSTCONDICIÓN** | Si la variación supera el umbral, se activa la actualización automática de precio. Si no, solo se registra el nuevo costo. |

| **NOMBRE:** | CU37 – Actualizar precio automáticamente |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Cuando la variación porcentual del costo supera el umbral configurado, el sistema actualiza el precio de venta aplicando el margen de ganancia definido. |
| **PRECONDICIONES:** | Umbral configurado (por defecto 3%). El producto tiene precio anterior y margen definido. |
| **FLUJO 		NORMAL:** | 1. Se detecta variación > umbral desde CU30. 
2. El sistema obtiene el margen de ganancia. 
3. Calcula nuevo precio = costo × (1 + margen). 
4. Actualiza el precio en la lista. 
5. Registra el cambio en histórico. 
6. Activa CU38 (notificación). |
| **FLUJOS 		ALTERNOS** | A. Sin margen definido: el sistema no actualiza y genera alerta al administrador. |
| **POSTCONDICIÓN** | Nuevo precio registrado. Administrador notificado. |

| **NOMBRE:** | CU38 – Notificar cambio de precio |
| --- | --- |
| **ACTORES:** | Sistema |
| **DESCRIPCIÓN:** | Caso de uso incluido por CU37. Notifica al administrador cuando el sistema ha actualizado automáticamente el precio de venta de un producto como consecuencia de una variación de costo. |
| **PRECONDICIONES:** | Se ha completado una actualización automática de precio (CU37). |
| **FLUJO 		NORMAL:** | 1. El sistema genera una notificación con el detalle del cambio: producto afectado, costo anterior, costo nuevo, precio anterior, precio nuevo y porcentaje de variación. 
2. Envía la notificación al dashboard de alertas del administrador. 
3. Registra la notificación en el histórico de cambios de precio. |
| **FLUJOS 		ALTERNOS** | A. Error de notificación: el sistema registra el error en logs pero el cambio de precio ya fue efectuado. |
| **POSTCONDICIÓN** | El administrador recibe la notificación en el dashboard. El cambio queda registrado en el histórico para auditoría. |

| **NOMBRE:** | CU39 – Registrar orden de producción |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite al administrador crear una orden de producción para un producto específico, indicando la cantidad a producir y la fecha programada. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. El producto tiene una formulación activa registrada. Existe disponibilidad de materias primas en inventario (no necesariamente suficiente). |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de órdenes de producción.
  2. Selecciona "Registrar orden de producción".
  3. Selecciona el producto a producir.
  4. Ingresa la cantidad a producir y la fecha programada.
  5. El sistema calcula automáticamente los requerimientos de materia prima según la **formulación activa** del producto.
  6. El sistema valida disponibilidad de stock.
  7. Registra la orden con estado "Pendiente".
  8. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Sin formulación activa: el sistema muestra error indicando que el producto no tiene formulación registrada.
B. Stock insuficiente: el sistema permite crear la orden pero muestra advertencia de faltantes.
C. Campos incompletos: el sistema resalta los campos obligatorios faltantes. |
| **POSTCONDICIÓN** | La orden de producción queda registrada en estado "Pendiente" con sus requerimientos asociados. |

| **NOMBRE:** | CU40 – Consultar orden de producción |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite consultar las órdenes de producción registradas, incluyendo su estado, producto, cantidad y fechas. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado.Existen órdenes de producción registradas. |
| **FLUJO 		NORMAL:** |   1. El usuario accede al módulo de órdenes de producción.
  2. El sistema muestra la lista de órdenes registradas.
  3. El usuario puede filtrar por estado (Pendiente, En proceso, Finalizada), producto o fecha.
  4. El sistema muestra los resultados con su información detallada.
  5. El usuario puede seleccionar una orden para ver su detalle. |
| **FLUJOS 		ALTERNOS** | A. Sin resultados: el sistema muestra "No se encontraron órdenes con los criterios ingresados". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU41 – Actualizar estado de orden |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Permite cambiar el estado de una orden de producción a lo largo de su ciclo de vida (Pendiente, En proceso, Finalizada, Cancelada). |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado.La orden de producción existe en el sistema. |
| **FLUJO 		NORMAL:** |   1. El usuario accede al módulo de órdenes de producción.
  2. Selecciona una orden.Selecciona la opción "Actualizar estado".
  3. Elige el nuevo estado.El sistema valida la transición de estado.
  4. Guarda el cambio.
  5. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Estado inválido: el sistema bloquea cambios no permitidos (ej: Finalizada → En proceso).
B. Orden inexistente: el sistema muestra error. |
| **POSTCONDICIÓN** | El estado de la orden queda actualizado correctamente. |

| **NOMBRE:** | CU42 – Asociar consumo de materia prima a la orden |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Extensión de CU12. Permite registrar y asociar el consumo real de materias primas a una orden de producción específica. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión. La orden está en estado "En proceso". Las materias primas existen en el sistema. |
| **FLUJO 		NORMAL:** | (Este flujo se activa cuando en CU12 se proporciona una referencia de orden) 
1. CU12 recibe referencia de orden válida. 
2. Al finalizar CU12, el sistema asocia el movimiento de consumo a la orden indicada. 
3. Actualiza el estado de consumo de la orden. |
| **FLUJOS 		ALTERNOS** | A. Referencia de orden inválida: el sistema muestra error y no asocia. |
| **POSTCONDICIÓN** | El consumo queda registrado y vinculado a la orden. |

| **NOMBRE:** | CU43 – Generar código QR por referencia de producto |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Genera un código QR único por producto que al escanearse redirige a la documentación técnica del envase. |
| **PRECONDICIONES:** | El producto existe en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de códigos QR. 
2. Selecciona un producto. 
3. El sistema genera un QR con URL única. 
4. Asocia la URL al producto. 
5. El administrador descarga el QR para impresión. |
| **FLUJOS 		ALTERNOS** | A. QR ya existente: el sistema pregunta si desea regenerarlo, advirtiendo que el anterior quedaría inválido
A. Producto inexistente: el sistema muestra error. |
| **POSTCONDICIÓN** | Producto asociado a código QR activo. URL disponible para consulta pública. |

| **NOMBRE:** | CU44 – Asociar documentación técnica |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite asociar documentos técnicos (ficha técnica, ficha de seguridad, certificados) a un código QR previamente generado. |
| **PRECONDICIONES:** | Existe un código QR generado para el producto. |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de códigos QR.
  2. Selecciona un producto con QR generado.
  3. Selecciona la opción "Asociar documentación".
  4. Carga los documentos técnicos requeridos.
  5. El sistema valida formato y tamaño de los archivos.
  6. Guarda los documentos asociados al código QR.
  7. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Formato inválido: el sistema muestra error y rechaza el archivo.
B. Archivo incompleto: el sistema solicita completar la información requerida. |
| **POSTCONDICIÓN** | Los documentos quedan asociados al código QR y disponibles para consulta. |

| **NOMBRE:** | CU45 - Actualizar documentos asociados |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite actualizar o reemplazar los documentos técnicos previamente asociados a un código QR. |
| **PRECONDICIONES:** | Existen documentos asociados al código QR. |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de códigos QR.
  2. Selecciona el producto o código QR.
  3. Visualiza los documentos asociados.
  4. Selecciona un documento a actualizar.
  5. Carga la nueva versión del documento.
  6. El sistema valida el archivo.
  7. Guarda la nueva versión y mantiene registro histórico.
  8. Muestra confirmación. |
| **FLUJOS 		ALTERNOS** | A. Documento inexistente: el sistema muestra error.
B. Error de carga: el sistema notifica y no guarda cambios. |
| **POSTCONDICIÓN** | El documento queda actualizado y el historial de versiones se conserva. |

| **NOMBRE:** | CU46 – Visualizar documentación desde QR |
| --- | --- |
| **ACTORES:** | Cliente, Comercial |
| **DESCRIPCIÓN:** | Permite acceder a la documentación técnica de un producto mediante el escaneo del código QR. |
| **PRECONDICIONES:** | El código QR ha sido generado y tiene documentos asociados.El usuario dispone de un dispositivo para escanear el código. |
| **FLUJO 		NORMAL:** |   1. El usuario escanea el código QR del producto.
  2. El sistema redirige a una página web asociada.
  3. El sistema muestra la documentación técnica disponible (ficha técnica, seguridad, certificados).
  4. El usuario puede visualizar o descargar los documentos. |
| **FLUJOS 		ALTERNOS** | A. QR sin documentos: el sistema muestra mensaje "No hay documentación disponible".
B. QR inválido: el sistema muestra error de acceso. |
| **POSTCONDICIÓN** | El usuario accede a la información técnica del producto sin alterar datos del sistema. |

| **NOMBRE:** | CU47 – Generar reporte de consumo de materia prima.  |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite generar un reporte detallado del consumo de materias primas en un período determinado, facilitando el análisis del uso de recursos. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión.Existen registros de consumo en el sistema. |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de reportes.
  2. Selecciona "Reporte de consumo de materia prima".
  3. Define el período de análisis.
  4. El sistema consulta el histórico de consumo (CU15).
  5. Genera el reporte con cantidades consumidas por materia prima.
  6. Muestra el reporte en pantalla.
  7. Permite exportar el reporte. |
| **FLUJOS 		ALTERNOS** | A. Sin datos: el sistema muestra "No hay registros para el período seleccionado". |
| **POSTCONDICIÓN** | El reporte queda disponible para consulta o exportación. |

| **NOMBRE:** | CU48 – Generar reporte de volumen de producción por periodo |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite visualizar la cantidad de producto fabricado en un período determinado, facilitando el análisis de la productividad. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión.Existen órdenes de producción finalizadas. |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de reportes.
  2. Selecciona "Reporte de producción".
  3. Define el período.
  4. El sistema consulta órdenes finalizadas (CU42 / CU41 según tu flujo).
  5. Calcula el volumen total producido por producto.
  6. Muestra el reporte.
  7. Permite exportar. |
| **FLUJOS 		ALTERNOS** | A. Sin producción registrada: el sistema muestra mensaje informativo. |
| **POSTCONDICIÓN** | El reporte queda disponible para análisis. |

| **NOMBRE:** | CU49 – Generar reporte de variación de costos |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite analizar la variación de costos de producción de los productos a lo largo del tiempo. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión.Existen registros en el histórico de costos. |
| **FLUJO 		NORMAL:** |   1. El administrador accede al módulo de reportes.
  2. Selecciona "Reporte de variación de costos".
  3. Define el producto y período.
  4. El sistema consulta el histórico de costos (CU31).
  5. Calcula variaciones porcentuales.
  6. Muestra resultados en tabla o gráfica.
  7. Permite exportar. |
| **FLUJOS 		ALTERNOS** | A. Sin historial: el sistema muestra mensaje informativo. |
| **POSTCONDICIÓN** | El reporte queda disponible para análisis. |

| **NOMBRE:** | CU50 – Visualizar dashboard de indicadores |
| --- | --- |
| **ACTORES:** | Administrador, Asistente de Producción |
| **DESCRIPCIÓN:** | Muestra un panel con indicadores clave: stock actual, alertas activas, consumo de materia prima, volúmenes de producción y variación de costos. |
| **PRECONDICIONES:** | El usuario ha iniciado sesión con rol habilitado para ver el dashboard. |
| **FLUJO 		NORMAL:** | 1. El actor accede al módulo de reportes. 
2. Selecciona "Dashboard". 
3. El sistema muestra gráficos e indicadores clave. 
4. El actor puede filtrar por período. 
5. Los datos se actualizan en tiempo real. |
| **FLUJOS 		ALTERNOS** | A. Sin datos en el período: el sistema muestra "No hay información disponible para el período seleccionado". |
| **POSTCONDICIÓN** | No hay cambios en los datos. |

| **NOMBRE:** | CU51 – Exportar datos de ventas para Power BI |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Exporta información de ventas en formato CSV/Excel compatible con Power BI, automatizando el proceso manual actual. |
| **PRECONDICIONES:** | Existen datos de ventas en el sistema. |
| **FLUJO 		NORMAL:** | 1. El administrador accede al módulo de exportaciones. 
2. Selecciona "Ventas" y define el período. 
3. El sistema genera el archivo CSV/Excel en UTF-8. 
4. El administrador descarga el archivo. |
| **FLUJOS 		ALTERNOS** | A. Sin datos: el sistema informa que no hay datos para el período seleccionado. |
| **POSTCONDICIÓN** | Archivo generado y disponible para consumo en Power BI. |

| **NOMBRE:** | CU52 – Exportar datos de producción para Power BI |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite exportar la información de producción para análisis externo. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. |
| **FLUJO 		NORMAL:** |   1. Accede al módulo de exportación.
  2. Selecciona "Exportar producción".
  3. Define período.
  4. El sistema genera archivo.
  5. Descarga. |
| **FLUJOS 		ALTERNOS** | A. Sin datos disponibles. |
| **POSTCONDICIÓN** | Archivo generado. |

| **NOMBRE:** | CU53 – Exportar datos de cartera para Power BI |
| --- | --- |
| **ACTORES:** | Administrador |
| **DESCRIPCIÓN:** | Permite exportar información financiera relacionada con cartera para su análisis en herramientas externas. |
| **PRECONDICIONES:** | El administrador ha iniciado sesión. |
| **FLUJO 		NORMAL:** |   1. Accede al módulo de exportación.
  2. Selecciona "Exportar cartera".
  3. Define filtros.
  4. El sistema genera archivo.
  5. Descarga. |
| **FLUJOS 		ALTERNOS** | A. Sin datos disponibles. |
| **POSTCONDICIÓN** | Archivo exportado correctamente. |
