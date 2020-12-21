const siteURL = "http://site5.ru";

// Удаляет старую и создаёт новую таблицу с заданными колонками
function createTable(columnsNames){
    let table = $("#dataTable");
    table.html('');                 // Очищаем таблицу
    let thead = $("<thead>");
    table.append(thead);            // Добавляем заголовок
    table.append($("<tbody>"));     // Добавляем основую часть таблицы
    let theadRow = $("<tr>");
    thead.append(theadRow);
    columnsNames.map(colName => {   // Добавляем столбцы:
        theadRow.append(
            // Создаём table header с необходимым текстом:
            $("<th>").text(colName)
        );
    });
}

// Добавляет в таблицу новую строку.
// columnsData - массив строк (длина должна совпадать с количеством столбцов)
function appendTable(columnsData){
    let tableRow = $("<tr>");
    $("#dataTable tbody").append(tableRow);
    columnsData.map(data =>{
        tableRow.append(
            $("<td>").text(data)
        );
    });
}

// Получает список названий колонок.
async function getColumnsNames(){
    return $.ajax({
        type: "POST",
        url: siteURL.concat("/getlogs.php"),
        data: "getColumnsNames=true",
        dataType: "json"
    });
}

$(() =>{
    // Добавление опций для select-а:
    getColumnsNames().then(data => {
        data.columnsNames.map(name => {
            $("#sortByColumn").append(
                $("<option>").val(name).text(name)
            );
            $("#groupByColumn").append(
                $("<option>").val(name).text(name)
            );
        });
    });
    $("#getLogs").on("click", (e) => {
        e.preventDefault();
        createTable(["Идёт получение данных..."]);
        
        // createTable(["test", "test2"]);
        // appendTable(["wowser", "cool"]);

        
        let data = {"form": {}};

        // data["form"] = data["form"].concat(
        //     {"logsCount": $("#logsCount").val()}
        // );
        data["form"]["logsCount"] = $("#logsCount").val();
        data["form"]["logsOffset"] = $("#logsOffset").val();
        data["form"]["sortByColumn"] = $("#sortByColumn").val() === "-" ? null : $("#sortByColumn").val();
        data["form"]["groupByColumn"] = $("#groupByColumn").val() === "-" ? null : $("#groupByColumn").val();
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
              console.log("Recieved: ", msg);
              if (msg.columnsNames !== undefined){
                  createTable(msg.columnsNames);
              }
              if (msg.rows !== undefined && msg.rows.length > 0){
                msg.rows.map(row => {
                    appendTable(Object.values(row));
                });
              } else if (msg.error !== undefined) {
                  appendTable(["При выборке произошла ошибка!"]);
              } else {
                  appendTable(["Текущие параметры вернули пустую выборку"]);
              }
            }
        });
    });
});