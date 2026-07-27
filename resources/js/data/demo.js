export const classes = [
    {
        id: 1,
        name: "Ấu Nhi 1A",
        level: "Ấu Nhi",
        students: 18,
        room: "Phòng A1",
        schedule: "Chủ nhật · 08:00",
        color: "bg-sky-100 text-sky-700",
    },
    {
        id: 2,
        name: "Thiếu Nhi 2B",
        level: "Thiếu Nhi",
        students: 22,
        room: "Phòng B2",
        schedule: "Chủ nhật · 09:30",
        color: "bg-indigo-100 text-indigo-700",
    },
    {
        id: 3,
        name: "Nghĩa Sĩ 1A",
        level: "Nghĩa Sĩ",
        students: 20,
        room: "Phòng C1",
        schedule: "Thứ bảy · 16:00",
        color: "bg-emerald-100 text-emerald-700",
    },
];
export const students = [
    ["Nguyễn An Bình", "Thánh Gioan", "Có mặt"],
    ["Trần Minh Châu", "Thánh Maria", "Có mặt"],
    ["Lê Gia Hân", "Thánh Anna", "Đi trễ"],
    ["Phạm Khánh Linh", "Thánh Têrêsa", "Có mặt"],
    ["Hoàng Quốc Bảo", "Thánh Phaolô", "Nghỉ có phép"],
    ["Đỗ Nhật Minh", "Thánh Giuse", "Có mặt"],
].map(([name, saint, status], i) => ({
    id: i + 1,
    name,
    saint,
    status,
    initials: name
        .split(" ")
        .slice(-2)
        .map((x) => x[0])
        .join(""),
}));
