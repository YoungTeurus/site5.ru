const siteURL = "http://site5.ru";

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// Worker, который проверяет прогресс загрузки логов в базу данных.
class ProgressChecker {
  constructor() {
    this.working = false; // Работает ли сейчас проверщик загрузки
  }

  start() {
    this.working = true;
    this.__worker();
  }

  stop() {
    this.working = false;
  }

  async __worker() {
    while (this.working) {
      await sleep(250).then(() => {
        this.__checkProgress();
      });
    }
  }

  __checkProgress() {
    $.ajax({
      type: "POST",
      url: siteURL.concat("/progress.php"),
      dataType: "json",
      success: (msg) => {
        if (msg.running) {
          setProgress(msg.rowsReady / msg.rowsToRead);
        }
      },
    });
  }
}

// Устанавливает значение progressbar-а
function setProgress(value) {
  $("#loadProgress").val(value);
}

$(() => {
  // Привязываем к кнопке обработчик событий, начинающий загрузку логов в БД:
  $("#startLoading").on("click", (e) => {
    e.preventDefault();
    let worker = new ProgressChecker();
    $("#startLoading")[0].disabled = true;
    $.ajax({
      type: "POST",
      url: siteURL.concat("/loadlogs.php"),
      data: `load=true&rows=${$("#rowsNumber").val()}`,
      dataType: "json",
      success: (msg) => {
        // Когда получили сообщение о загрузке:
        if (msg.load == true) {
          $("#result").text(`Было успешно загружено ${msg.rowsLoaded} строк!`);
        } else {
          $("#result").text(`При загрузке произошла ошибка!`);
        }
        // Останавливаем прослушивателя прогресса:
        worker.stop();
        // Устанавливаем прогресс в 100%
        setProgress(1);
        // Разблокируем кнопку:
        $("#startLoading")[0].disabled = false;
      },
    });
    // После старта устанавливаем прогресс в 0%
    setProgress(0);
    // Запускаем прослушиватель прогресса:
    worker.start();
  });
});

// Способ получить строку со всеми ключами и значениями JSON-а:
// msgStr = "";
// for (const [key, value] of Object.entries(msg)) {
//   msgStr = msgStr.concat(key).concat(":").concat(value).concat("\n");
// }
