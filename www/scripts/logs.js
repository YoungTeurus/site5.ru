const siteURL = "http://site5.ru";

// Удаляет старую и создаёт новую таблицу с заданными колонками
function createTable(columnsNames) {
  let table = $("#dataTable");
  table.html(""); // Очищаем таблицу
  let thead = $("<thead>");
  table.append(thead); // Добавляем заголовок
  table.append($("<tbody>")); // Добавляем основую часть таблицы
  let theadRow = $("<tr>");
  thead.append(theadRow);
  columnsNames.map((colName) => {
    // Добавляем столбцы:
    theadRow.append(
      // Создаём table header с необходимым текстом:
      $("<th>").text(colName)
    );
  });
}

// Добавляет в таблицу новую строку.
// columnsData - массив строк (длина должна совпадать с количеством столбцов)
function appendTable(columnsData) {
  let tableRow = $("<tr>");
  $("#dataTable tbody").append(tableRow);
  columnsData.map((data) => {
    tableRow.append($("<td>").text(data));
  });
}

// Получает список названий колонок.
async function getColumnsNames() {
  return $.ajax({
    type: "POST",
    url: siteURL.concat("/getlogs.php"),
    data: "getColumnsNames=true",
    dataType: "json",
  });
}

$(() => {
  // Добавление опций для select-а:
  getColumnsNames().then((data) => {
    data.columnsNames.map((name) => {
      $("#sortByColumn").append($("<option>").val(name).text(name));
      $("#groupByColumn").append($("<option>").val(name).text(name));
    });
  });
  $("#getLogs").on("click", (e) => {
    e.preventDefault();

    // Создание таблицы-placeholder-а
    createTable(["Идёт получение данных..."]);

    // Сбор данных для отправки на сервер:
    let data = {
      form: {},
    };
    data["form"]["logsCount"] = $("#logsCount").val();
    data["form"]["logsOffset"] = $("#logsOffset").val();
    data["form"]["sortByColumn"] =
      $("#sortByColumn").val() === "-" ? null : $("#sortByColumn").val();
    data["form"]["groupByColumn"] =
      $("#groupByColumn").val() === "-" ? null : $("#groupByColumn").val();
    data["form"]["sortGroupByColumn"] = $("#sortGroupByColumn").val();
    data["form"]["descendSortOrder"] = $("#descendSortOrder")[0].checked;
    data["form"]["descendGroupOrder"] = $("#descendGroupOrder")[0].checked;

    console.log("Sent: ", data);
    $.ajax({
      type: "POST",
      url: siteURL.concat("/getlogs.php"),
      data: data,
      dataType: "json",
      success: (msg) => {
        // Когда получили данные:
        console.log("Recieved: ", msg);
        // Если есть колонки, создаём таблицу и заполняем её:
        if (msg.columnsNames !== undefined) {
          createTable(msg.columnsNames);
          if (msg.rows !== undefined && msg.rows.length > 0) {
            msg.rows.map((row) => {
              appendTable(Object.values(row));
            });
          } else {
            // Если выборка вернула пустое множество:
            appendTable(["Текущие параметры вернули пустую выборку"]);
          }
        } else if (msg.error !== undefined) {
          // Если получили ошибку:
          appendTable(["При выборке произошла ошибка!"]);
        } else {
          // Если получили ошибку, которую не обработал PHP:
          appendTable(["При выборке произошла непредвиденная ошибка!"]);
        }
      },
    });
  });
});
