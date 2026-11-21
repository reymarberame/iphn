let controller = null;

export function setCallController(next) {
    controller = next;
}

export function getCallController() {
    return controller;
}
